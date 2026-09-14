<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Models\User;
use App\Modules\Addons\Roadmap\Controllers\RoadmapController;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * #9991137 — Medidor mecánico del DISPARADOR de la deuda #9991129 (Hoja de ruta: contadores por
 * endpoint de agregados + lista filtrada/paginada server-side). Irving fijó (2026-09-14) que esa
 * deuda se ejecuta cuando se cumpla CUALQUIERA de estas tres:
 *   a) universo > 4,000 items
 *   b) payload COMPRIMIDO de GET /api/roadmap/items > 2 MB (lo que recibe el navegador, no el crudo)
 *   c) tiempo de respuesta de RoadmapController::index() > 1,500 ms
 * (c) existe porque gzip no toca el costo de consulta+serialización: es la métrica que se degrada
 * primero y la que pasaría desapercibida vigilando solo el tamaño.
 *
 * Mide las tres contra el endpoint REAL (index() en proceso, usuario con roadmap_view, gzip al
 * nivel configurado en nginx) y devuelve exit 1 si alguna se cumple — así el chequeo no depende de
 * que alguien "sienta lenta" la Torre. Línea base del 2026-09-14: 1,775 items · 0.44 MB gzip
 * (2.16 MB crudo) · 616 ms (mediana de 3).
 */
class MedirHojaDeRutaCommand extends Command
{
    protected $signature = 'roadmap:medir-hoja-de-ruta
        {--umbral-items=4000 : disparador (a), items del universo}
        {--umbral-mb=2 : disparador (b), MB del payload comprimido}
        {--umbral-ms=1500 : disparador (c), ms de index()}
        {--corridas=3 : corridas de index() para la mediana de ms}
        {--json : salida para máquinas}';

    protected $description = '#9991137 — mide las 3 métricas del disparador de #9991129 (universo, payload gzip, ms de index()); exit 1 si alguna se cumple.';

    public function handle(): int
    {
        $user = User::role('super-administrator')->first()
            ?? User::permission('roadmap_view')->first();
        if (! $user) {
            $this->error('No hay usuario con roadmap_view para ejecutar index().');

            return self::FAILURE;
        }
        Auth::login($user);

        $controller = app(RoadmapController::class);
        $corridas   = max(1, (int) $this->option('corridas'));
        $ms = [];
        $json = '';
        for ($k = 0; $k < $corridas; $k++) {
            $t0   = microtime(true);
            $resp = $controller->index(Request::create('/api/roadmap/items', 'GET'));
            $ms[] = (int) round((microtime(true) - $t0) * 1000);
            $json = $resp->getContent();
        }
        sort($ms);
        $mediana = $ms[intdiv(count($ms), 2)];

        $nivel   = $this->nivelGzipNginx();
        $crudo   = strlen($json);
        $gzip    = strlen(gzencode($json, $nivel));
        $items   = RoadmapItem::count();
        $enPayload = count(json_decode($json, true) ?: []);

        $umbralItems = (int) $this->option('umbral-items');
        $umbralBytes = (float) $this->option('umbral-mb') * 1048576;
        $umbralMs    = (int) $this->option('umbral-ms');

        $disparado = [
            'a_universo'         => $items > $umbralItems,
            'b_payload_gzip'     => $gzip > $umbralBytes,
            'c_ms_index'         => $mediana > $umbralMs,
        ];
        $alguno = in_array(true, $disparado, true);

        $out = [
            'fecha'                 => now()->toIso8601String(),
            'universo_items'        => $items,
            'items_en_payload'      => $enPayload,
            'payload_crudo_bytes'   => $crudo,
            'payload_gzip_bytes'    => $gzip,
            'gzip_nivel'            => $nivel,
            'index_ms_corridas'     => $ms,
            'index_ms_mediana'      => $mediana,
            'umbrales'              => ['items' => $umbralItems, 'gzip_bytes' => (int) $umbralBytes, 'ms' => $umbralMs],
            'disparado'             => $disparado,
            'ejecutar_9991129'      => $alguno,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $alguno ? self::FAILURE : self::SUCCESS;
        }

        $mb = fn (int $b) => number_format($b / 1048576, 2);
        $this->line('Disparador de #9991129 — Hoja de ruta (' . $out['fecha'] . ')');
        $this->table(
            ['métrica', 'medido', 'umbral', 'estado'],
            [
                ['(a) universo (roadmap_items)', number_format($items) . " items ({$enPayload} en el payload)", '> ' . number_format($umbralItems), $disparado['a_universo'] ? '🔴 SE CUMPLE' : '🟢 ok'],
                ['(b) payload gzip de /api/roadmap/items', $mb($gzip) . " MB (crudo {$mb($crudo)} MB, gzip nivel {$nivel})", '> ' . $this->option('umbral-mb') . ' MB', $disparado['b_payload_gzip'] ? '🔴 SE CUMPLE' : '🟢 ok'],
                ['(c) index() ms (mediana de ' . count($ms) . ')', "{$mediana} ms (" . implode('/', $ms) . ')', "> {$umbralMs} ms", $disparado['c_ms_index'] ? '🔴 SE CUMPLE' : '🟢 ok'],
            ]
        );
        $this->line($alguno
            ? '🔴 Al menos un disparador se cumple: toca ejecutar la deuda #9991129 (exit 1).'
            : '🟢 Ningún disparador se cumple: #9991129 sigue en espera (exit 0).');

        return $alguno ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Nivel de compresión que nginx aplica de verdad (último `gzip_comp_level` no comentado en
     * nginx.conf o conf.d/*.conf). Sin directiva, nginx usa 1. Si /etc/nginx no es legible se
     * asume el 4 del drop-in de dev (deploy/nginx-gzip-dev.conf) y se avisa.
     */
    private function nivelGzipNginx(): int
    {
        $archivos = array_merge(['/etc/nginx/nginx.conf'], glob('/etc/nginx/conf.d/*.conf') ?: []);
        $nivel = null;
        $legible = false;
        foreach ($archivos as $f) {
            if (! is_readable($f)) {
                continue;
            }
            $legible = true;
            foreach (file($f) as $linea) {
                if (preg_match('/^\s*gzip_comp_level\s+(\d)\s*;/', $linea, $m)) {
                    $nivel = (int) $m[1];
                }
            }
        }
        if (! $legible) {
            $this->warn('No se pudo leer /etc/nginx: se asume gzip_comp_level 4 (drop-in de dev).');

            return 4;
        }

        return $nivel ?? 1;
    }
}
