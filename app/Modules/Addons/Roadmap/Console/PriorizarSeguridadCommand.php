<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Console\Command;

/**
 * PASADA DE PRIORIZACIÓN POR RIESGO (#334). Barre el backlog pendiente, pre-filtra por señales de
 * SEGURIDAD/DINERO, y con Opus clasifica cada candidato. Los de seguridad/dinero REALES suben a
 * prioridad ALTA, se marcan "atacar primero" (⚡[SEG-TOP]/⚡[DINERO-TOP]) y se escalan a la bandeja
 * con un FIX + BRIEF de Opus. Deja SEPARADOS [BLOCKED-NEGOCIO] (decisión de Irving) y [PARKED-PROD]
 * (prod, no tocar). La masa técnica sigue por prioridad normal.
 *
 * FRONTERA DURA: seguridad/dinero/negocio/prod NO se auto-ejecutan; van a Irving. Solo dev.
 * Idempotente: sella cada item procesado (⟪SEG-TRIAGE⟫) y lo salta en re-corridas → drenable por cron.
 */
class PriorizarSeguridadCommand extends Command
{
    protected $signature = 'circuito:priorizar-seguridad {--limit=8 : máximo de candidatos a triar por pasada} {--dry : solo reporta, no aplica}';

    protected $description = 'Sube seguridad/dinero a ALTA + fix/brief de Opus + escala; separa negocio/prod (#334).';

    private const MARCA = '⟪SEG-TRIAGE⟫';

    private const LOCK = '/home/meganet/circuito/priorizar-seg.lock';

    /** Señales de pre-filtro (título+descripción+prompt+comentarios, sin acentos). */
    private const SENALES = [
        'texto plano', 'plaintext', 'base64', 'bcrypt', 'hash', 'contrasen', 'password',
        'account takeover', 'takeover', 'suplant', 'idor', 'pii', 'lfpdppp', 'datos personales',
        'bypass', 'sin (permiso|gate|auth|authorize|login|sesion)', 'authorize', 'check_route_permission',
        'rol nuevo', 'syncroles', 'syncpermissions', 'privilegio', 'todos los permisos', 'super.?admin',
        'register', 'registro publico', 'webhook', 'hmac', 'firma', 'forjable',
        'credencial', 'secret', 'api.?key', 'hardcode', 'expuest',
        'doble cobro', 'idempoten', 'cobro.{0,10}duplicad', 'pago.{0,10}duplicad', 'recobr', 'add_by=0',
        'saldo.{0,10}(incorrect|doble|negativ)', 'doble submit', 'doble aplicaci',
    ];

    public function handle(RevisorService $revisor): int
    {
        // Un solo triaje a la vez (evita que el cron y una corrida manual doble-briefen el mismo item).
        @mkdir(dirname(self::LOCK), 0775, true);
        $lock = @fopen(self::LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->info('Priorización de riesgo: otra pasada en curso (lock ocupado).');
            return self::SUCCESS;
        }

        try {
            return $this->correr($revisor);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function correr(RevisorService $revisor): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $re = '~(' . implode('|', self::SENALES) . ')~i';
        $norm = fn (string $s) => strtr(mb_strtolower($s), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);

        // Candidatos: pendientes, aún SIN procesar por esta pasada, que disparan alguna señal.
        $pend = RoadmapItem::whereNotIn('status', ['done', 'cancelado'])
            ->where('en_desarrollo_humano', false)
            ->where(function ($q) {
                $q->whereNull('comentarios_claude')->orWhere('comentarios_claude', 'not like', '%' . self::MARCA . '%');
            })
            ->orderByRaw("FIELD(estado_aprobacion,'requiere_irving','pendiente_revision') DESC")
            ->orderByDesc('id')
            ->get();

        $cands = $pend->filter(function (RoadmapItem $it) use ($re, $norm) {
            $blob = $norm(($it->title ?? '') . ' ' . ($it->description ?? '') . ' ' . ($it->prompt ?? '') . ' ' . ($it->comentarios_claude ?? ''));
            return (bool) preg_match($re, $blob);
        })->take($limit);

        if ($cands->isEmpty()) {
            $this->info('Priorización de riesgo: sin candidatos nuevos.');
            return self::SUCCESS;
        }

        $cont = ['seguridad' => 0, 'dinero' => 0, 'negocio' => 0, 'prod' => 0, 'no_aplica' => 0];
        foreach ($cands as $item) {
            $r = $revisor->briefarSeguridad($item);
            $cat = $r['categoria'];
            $cont[$cat] = ($cont[$cat] ?? 0) + 1;

            if ($this->option('dry')) {
                $this->line("#{$item->id} → {$cat}/{$r['severidad']} · " . mb_strimwidth((string) $r['titulo_corto'], 0, 50, '…'));
                continue;
            }

            $this->aplicar($item, $r);
            $etq = in_array($cat, ['seguridad', 'dinero'], true) ? "⚡ {$cat}/{$r['severidad']}" : $cat;
            $this->line("#{$item->id} → {$etq} · " . mb_strimwidth((string) $r['titulo_corto'], 0, 50, '…'));
        }

        $this->info('Priorización de riesgo: seguridad ' . $cont['seguridad'] . ' · dinero ' . $cont['dinero']
            . ' · negocio ' . $cont['negocio'] . ' · prod ' . $cont['prod'] . ' · no_aplica ' . $cont['no_aplica'] . '.');

        return self::SUCCESS;
    }

    /** Aplica la clasificación al item (prioridad/estado/tag/brief), idempotente por la marca. */
    private function aplicar(RoadmapItem $item, array $r): void
    {
        $cat = $r['categoria'];
        $tag = match ($cat) {
            'seguridad' => '⚡[SEG-TOP · ' . ($r['subcat'] ?: 'seguridad') . '/' . $r['severidad'] . ']',
            'dinero'    => '⚡[DINERO-TOP · ' . ($r['subcat'] ?: 'dinero') . '/' . $r['severidad'] . ']',
            'negocio'   => '[BLOCKED-NEGOCIO]',
            'prod'      => '[PARKED-PROD]',
            default     => null,
        };

        // SEGURIDAD/DINERO real → ALTA + atacar primero + escalar a la bandeja.
        if (in_array($cat, ['seguridad', 'dinero'], true)) {
            $item->priority = 'alta';
            $item->estado_aprobacion = 'requiere_irving';   // frontera dura → a Irving
            $item->aprobado_por = 'priorizacion-riesgo(opus)';
        }
        // negocio/prod: NO se cambia prioridad ni estado (solo se etiqueta y separa).

        $sello = "\n\n--- FIX + BRIEF DE RIESGO (Opus) " . now()->toDateTimeString() . " " . self::MARCA . " ---\n"
            . ($tag ? $tag . "\n" : '')
            . trim((string) $r['texto_brief']) . "\n";
        $item->comentarios_claude = (string) $item->comentarios_claude . $sello;
        $item->revisado_at = now();
        $item->save();
    }
}
