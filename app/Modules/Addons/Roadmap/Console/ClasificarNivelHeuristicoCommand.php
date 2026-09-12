<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * PASO 3 de #9990861 (#9990875) — backfill de `nivel_riesgo` para los items YA CERRADOS
 * (completado|cancelado) que llegaron sin clasificar. CERO impacto en el pool/dispatch: un
 * item cerrado nunca vuelve a reclamarse, así que esto es solo trazabilidad histórica.
 *
 * Decisión ya tomada por el autopilot en el brief del propio item (q1, Opción 1, confianza
 * alta + reversible): clasificación HEURÍSTICA por módulo/título/keywords, backfill en una
 * sola pasada, con log auditable por item y `clasificacion_metodo='heuristica'`.
 *
 * Criterio (mismo de CLAUDE.md, MÁS RESTRICTIVO ante la duda):
 *   C = toca dinero/permisos/auth/seguridad real
 *   B = toca migraciones de esquema/infra real (mikrotik/OLT/routers/producción) o UI sensible
 *   A = resto (aditivo/reversible, sin tocar lo anterior)
 *
 * Idempotente: solo toca items con `nivel_riesgo` NULL (correr 2 veces no cambia nada el 2do run).
 */
class ClasificarNivelHeuristicoCommand extends Command
{
    protected $signature = 'circuito:clasificar-nivel-heuristico
        {--sid= : tu slot de terminal (wt-K), solo para el log}
        {--dry-run : solo reporta el conteo por nivel, no escribe nada}';

    protected $description = 'PASO 3 de #9990861 (#9990875) — backfill heurístico de nivel_riesgo en items ya CERRADOS sin clasificar.';

    /** Estados "cola viva": si el item está en alguno de estos, NUNCA se toca aquí. */
    private const COLA_VIVA = [
        'pendiente_revision', 'aprobado_claude', 'aprobado_revisor',
        'requiere_irving', 'aprobado_irving', 'en_progreso',
    ];

    /** Dinero/permisos/auth/seguridad real → C. Boundary solo al inicio (capta plurales/conjugaciones). */
    private const PALABRAS_C = [
        'dinero', 'pago', 'pagos', 'cobro', 'cobranza', 'facturaci', 'factura fiscal', 'cfdi', 'timbrad',
        'comisi', 'nomina', 'nómina', 'salario', 'openpay', 'domiciliaci', 'spei', 'saldo', 'deuda',
        'credencial', 'contraseña', 'password', 'secreto', 'api key', 'token de acceso',
        'permiso', 'rol de', 'roles de', 'autenticaci', 'autoriza', 'auth',
        'seguridad', 'vulnerabilidad', 'sqli', 'inyecci', 'xss', 'csrf', 'cifrado', 'encriptaci',
    ];

    /** Migraciones/infra real/producción/UI sensible → B. */
    private const PALABRAS_B = [
        'migracion', 'migración', 'esquema de bd', 'alter table', 'drop table',
        'despliegue', 'deploy', 'producc',
        'mikrotik', 'router', 'asterisk', ' olt ', 'onu', 'firmware', 'ruptela', 'gps real',
        'sidebar', 'formulario', 'pantalla', 'interfaz',
    ];

    /** Módulos cuyo default (sin match de keyword) es más restrictivo que A. */
    private const MODULO_DEFAULT_C = ['Permisos / Roles'];
    private const MODULO_DEFAULT_B = ['Red / IPv6', 'Deploy / Releases'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $items = RoadmapItem::query()
            ->whereNull('nivel_riesgo')
            ->whereNotIn('estado_aprobacion', self::COLA_VIVA)
            ->get(['id', 'modulo', 'title', 'description', 'prompt', 'comentarios_claude', 'nivel_riesgo_origen', 'log']);

        $conteo = ['A' => 0, 'B' => 0, 'C' => 0];

        foreach ($items as $item) {
            $nivel = $this->clasificar((string) $item->modulo, $this->texto($item));
            $conteo[$nivel]++;

            if ($dryRun) {
                continue;
            }

            $item->nivel_riesgo = $nivel;
            if (! $item->nivel_riesgo_origen) {
                $item->nivel_riesgo_origen = 'interno';
            }
            $item->clasificacion_metodo = 'heuristica';

            $log   = is_array($item->log) ? $item->log : [];
            $log[] = [
                'ts'     => now()->toIso8601String(),
                'por'    => 'circuito:clasificar-nivel-heuristico',
                'evento' => 'clasificacion_heuristica_nivel_riesgo',
                'nivel_riesgo' => $nivel,
                'motivo' => 'Backfill PASO 3 de #9990861 (#9990875): item ya cerrado, sin impacto en pool.',
            ];
            $item->log = $log;

            $item->save();
        }

        $total = array_sum($conteo);
        $modo  = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$modo}Clasificados {$total} item(s): A={$conteo['A']} B={$conteo['B']} C={$conteo['C']}");

        return self::SUCCESS;
    }

    private function texto(RoadmapItem $item): string
    {
        $crudo = implode(' ', [
            (string) $item->title,
            (string) $item->description,
            (string) $item->prompt,
            (string) $item->comentarios_claude,
        ]);

        $texto = Str::lower($crudo);

        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private function clasificar(string $modulo, string $texto): string
    {
        if ($this->contiene($texto, self::PALABRAS_C)) {
            return 'C';
        }

        if (in_array($modulo, self::MODULO_DEFAULT_C, true)) {
            return 'C';
        }

        if ($this->contiene($texto, self::PALABRAS_B)) {
            return 'B';
        }

        if (in_array($modulo, self::MODULO_DEFAULT_B, true)) {
            return 'B';
        }

        return 'A';
    }

    private function contiene(string $texto, array $palabras): bool
    {
        foreach ($palabras as $p) {
            $normal = strtr(Str::lower($p), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
            if (preg_match('/\b' . preg_quote($normal, '/') . '/u', $texto) === 1) {
                return true;
            }
        }

        return false;
    }
}
