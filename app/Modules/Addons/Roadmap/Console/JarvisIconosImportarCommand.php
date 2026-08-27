<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\JarvisIconosService;
use Illuminate\Console\Command;

/**
 * Mete los PNG de la carpeta de origen al set versionado de JARVIS, en sus cuatro tamaños.
 *
 * POR QUÉ UN COMANDO Y NO UNA SUBIDA DESDE EL PANEL (decisión de Irving, 2026-08-27): se elige
 * entre un set FIJO, no se cargan archivos nuevos desde la web. Menos superficie que asegurar —cero
 * validación de binarios llegados por HTTP— y la identidad queda en git como cualquier otro cambio.
 *
 * IDEMPOTENTE y NO PISA EL CRITERIO HUMANO: si un icono ya está en el manifiesto, se conservan sus
 * campos `tiene_texto`, `legible_48` y `nota` tal como estén. Eso importa porque esos dos campos
 * son un juicio VISUAL —«¿a 48 px esto sigue leyéndose o es ruido?»— y ninguna heurística lo
 * sustituye: el comando pone un valor de arranque y la persona lo corrige.
 */
class JarvisIconosImportarCommand extends Command
{
    protected $signature = 'jarvis:iconos-importar
        {--origen= : Carpeta con los PNG (por defecto la del servicio)}
        {--forzar : Regenera los PNG aunque ya existan}';

    protected $description = 'Importa los PNG de JARVIS y genera 48/96/192/512 px de cada uno.';

    public function handle(JarvisIconosService $svc): int
    {
        $origen = rtrim((string) ($this->option('origen') ?: JarvisIconosService::DIR_ORIGEN), '/');

        if (! is_dir($origen)) {
            $this->error("No existe la carpeta de origen: {$origen}");
            $this->line('Créala y deja ahí los PNG (un archivo por diseño).');

            return self::FAILURE;
        }

        if (! extension_loaded('gd')) {
            $this->error('Falta la extensión GD de PHP: sin ella no se pueden generar los tamaños.');

            return self::FAILURE;
        }

        $fuentes = glob($origen . '/*.{png,PNG}', GLOB_BRACE) ?: [];
        if ($fuentes === []) {
            $this->warn("No hay PNG en {$origen}. Nada que importar.");
            $this->line('El selector va a decir que no hay catálogo, que es la verdad.');

            return self::SUCCESS;
        }

        // Lo que ya estaba: se respeta el juicio humano de los dos campos visuales.
        $previos = [];
        foreach ((array) ($svc->manifiesto()['iconos'] ?? []) as $ic) {
            if (is_array($ic) && ! empty($ic['slug'])) {
                $previos[$ic['slug']] = $ic;
            }
        }

        $iconos = [];
        foreach ($fuentes as $ruta) {
            $base = pathinfo($ruta, PATHINFO_FILENAME);
            $slug = $this->slug($base);
            if ($slug === '') {
                $this->warn("Nombre no utilizable, se salta: {$ruta}");
                continue;
            }

            $src = @imagecreatefrompng($ruta);
            if (! $src) {
                $this->warn("No es un PNG legible, se salta: {$ruta}");
                continue;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $destDir = public_path(JarvisIconosService::DIR_PUBLICO . '/' . $slug);
            if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
                $this->error("No se pudo crear {$destDir}");
                imagedestroy($src);
                continue;
            }

            foreach (JarvisIconosService::TAMANOS as $px) {
                $destino = $destDir . "/{$px}.png";
                if (is_file($destino) && ! $this->option('forzar')) {
                    continue;
                }
                $this->escalarCuadrado($src, $w, $h, $px, $destino);
            }
            imagedestroy($src);

            $previo = $previos[$slug] ?? [];
            $iconos[] = [
                'slug'   => $slug,
                'nombre' => $previo['nombre'] ?? $this->titulo($base),
                'origen' => basename($ruta),
                'fuente' => ['ancho' => $w, 'alto' => $h],
                // VALORES DE ARRANQUE, no veredictos. `tiene_texto` se adivina por el nombre del
                // archivo (si trae «texto»/«wordmark»/«jarvis-escrito»); `legible_48` arranca en
                // true. Los dos se corrigen a ojo — que es la única forma de saberlo.
                'tiene_texto' => array_key_exists('tiene_texto', $previo)
                    ? (bool) $previo['tiene_texto']
                    : (bool) preg_match('/(texto|wordmark|escrit|letras|logo-?tipo)/i', $base),
                'legible_48'  => array_key_exists('legible_48', $previo) ? (bool) $previo['legible_48'] : true,
                'nota'        => $previo['nota'] ?? null,
                'revisado'    => (bool) ($previo['revisado'] ?? false),
            ];

            $this->line("  ✓ {$slug}  ({$w}×{$h})  →  " . implode(', ', JarvisIconosService::TAMANOS) . ' px');
        }

        usort($iconos, fn ($a, $b) => strcmp($a['slug'], $b['slug']));

        $manifiesto = $svc->manifiesto();
        $manifiesto['generado_en'] = now()->toDateTimeString();
        $manifiesto['iconos'] = $iconos;

        file_put_contents(
            $svc->rutaManifiesto(),
            json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $this->newLine();
        $this->info(count($iconos) . ' icono(s) en el catálogo.');
        $sinRevisar = count(array_filter($iconos, fn ($i) => ! $i['revisado']));
        if ($sinRevisar > 0) {
            $this->warn("{$sinRevisar} sin revisar a ojo: mira el 48 px de cada uno y corrige "
                . '`legible_48` / `tiene_texto` en el manifiesto. El valor de arranque es una '
                . 'suposición, no un veredicto.');
        }

        return self::SUCCESS;
    }

    /**
     * Escala a un cuadrado de $px conservando proporción y centrando, con fondo TRANSPARENTE.
     *
     * Cuadrado y no recorte: la burbuja es redonda y un recorte agresivo se come justo los bordes
     * del diseño, que es donde suelen estar los trazos que lo hacen reconocible.
     */
    private function escalarCuadrado($src, int $w, int $h, int $px, string $destino): void
    {
        $dst = imagecreatetruecolor($px, $px);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, true);

        $escala = min($px / max($w, 1), $px / max($h, 1));
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        imagecopyresampled($dst, $src, (int) (($px - $nw) / 2), (int) (($px - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        imagepng($dst, $destino, 9);
        imagedestroy($dst);
        @chmod($destino, 0664);
    }

    private function slug(string $base): string
    {
        $s = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($base));

        return trim((string) $s, '-');
    }

    private function titulo(string $base): string
    {
        return ucfirst(trim(preg_replace('/[^a-z0-9]+/i', ' ', $base)));
    }
}
