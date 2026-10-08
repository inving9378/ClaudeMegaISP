<?php

namespace App\Console\Commands\Active;

use App\Models\User;
use App\Services\Asistente\Entrenamiento\Evaluador;
use Illuminate\Console\Command;

/**
 * Pone a prueba a JARVIS (el robotsito) con la batería de resources/asistente/pruebas.json: cada
 * prueba es una plática real con la IA, dentro de una transacción que se deshace. Sirve para pulir
 * sus instrucciones y guías y para comprobar que un cambio no lo empeoró. Gasta IA (poco) en cada
 * corrida; el reporte completo queda en storage/logs/asistente-evaluacion-*.json.
 */
class AsistenteEvaluarCommand extends Command
{
    protected $signature = 'asistente:evaluar
        {ids?* : Solo estas pruebas (su id)}
        {--tema= : Solo las pruebas de ese tema}
        {--detalle : Mostrar la respuesta completa de cada prueba}';

    protected $description = 'JARVIS: corre su batería de pruebas con la IA de verdad y dice qué falla';

    public function handle(Evaluador $evaluador): int
    {
        $config = json_decode((string) @file_get_contents(resource_path('asistente/pruebas.json')), true) ?: [];
        $pruebas = collect($config['pruebas'] ?? [])
            ->when($this->argument('ids'), fn ($c, $ids) => $c->whereIn('id', $ids))
            ->when($this->option('tema'), fn ($c, $t) => $c->filter(fn ($p) => mb_strtolower($p['tema'] ?? '') === mb_strtolower($t)))
            ->values();
        if ($pruebas->isEmpty()) {
            $this->error('No hay pruebas con ese filtro.');

            return self::FAILURE;
        }

        $base = User::where('login_user', $config['usuario'] ?? '')->first();
        if (!$base) {
            $this->error("No existe el usuario de pruebas «" . ($config['usuario'] ?? '') . '» (resources/asistente/pruebas.json).');

            return self::FAILURE;
        }

        $resultados = [];
        foreach ($pruebas as $p) {
            $usuario = !empty($p['usuario_rol']) ? $this->usuarioConRol($p['usuario_rol']) : $base;
            if (!$usuario) {
                $this->warn("{$p['id']}: no hay un usuario activo con el rol {$p['usuario_rol']}; se omite.");
                continue;
            }
            $r = $evaluador->correr($p, $usuario);
            $resultados[] = $r;

            $this->line(($r['ok'] ? '<info>✔</info>' : '<error>✘</error>') . " {$r['id']} <comment>({$r['segundos']} s · "
                . ($r['herramientas'] ? implode(', ', array_unique($r['herramientas'])) : 'sin herramientas')
                . ($r['accion'] ? " · preparó {$r['accion']}" : '') . ')</comment>');
            foreach ($r['fallas'] as $f) {
                $this->line("    <error>•</error> {$f}");
            }
            foreach ($r['avisos'] as $a) {
                $this->line("    <comment>•</comment> {$a}");
            }
            if ($this->option('detalle') || !$r['ok']) {
                $this->line('    > ' . str_replace("\n", "\n    > ", trim($r['respuesta'])));
            }
        }

        $ok = count(array_filter($resultados, fn ($r) => $r['ok']));
        $total = count($resultados);
        $this->newLine();
        foreach (collect($resultados)->groupBy('tema') as $tema => $rs) {
            $this->line(sprintf('  %-22s %d/%d', $tema, $rs->where('ok', true)->count(), $rs->count()));
        }
        $this->info("Pasaron {$ok} de {$total}.");

        $archivo = storage_path('logs/asistente-evaluacion-' . now()->format('Ymd-His') . '.json');
        file_put_contents($archivo, json_encode(['fecha' => now()->toDateTimeString(), 'pasaron' => $ok, 'total' => $total, 'resultados' => $resultados],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("Reporte: {$archivo}");

        return $ok === $total ? self::SUCCESS : self::FAILURE;
    }

    private function usuarioConRol(string $rol): ?User
    {
        $admin = ['client', 'super-administrator', 'DESARROLLADOR', 'Super Administrador', 'Administrador'];

        return User::role($rol)->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $admin))
            ->where('estado', 'activo')->orderBy('id')->first();
    }
}
