<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Campañas 360 (Fase 2): reproducir un video producido y su miniatura (§2.4.13–14). Solo videos `listo` de la campaña;
 * la ruta del archivo se resuelve con `realpath` y debe quedar dentro de `storage/app/marketing/videos` (si no, 404).
 */
class Campana360VideoController extends Controller
{
    /** 13) GET /api/marketing/campanas-360/{id}/videos/{videoId}/ver — video/mp4 en línea (admite Range). */
    public function ver(int $id, int $videoId): BinaryFileResponse|JsonResponse
    {
        return $this->archivo($id, $videoId, 'output_path', 'video/mp4');
    }

    /** 14) GET /api/marketing/campanas-360/{id}/videos/{videoId}/miniatura — image/jpeg. */
    public function miniatura(int $id, int $videoId): BinaryFileResponse|JsonResponse
    {
        return $this->archivo($id, $videoId, 'thumbnail_path', 'image/jpeg');
    }

    private function archivo(int $id, int $videoId, string $columna, string $tipo): BinaryFileResponse|JsonResponse
    {
        $campana = Campana360Controller::buscar($id);
        if (! $campana) {
            return Campana360Controller::noEncontrada();
        }

        $video = GeneratedContent::query()
            ->whereKey($videoId)
            ->where('source_campaign_id', $campana->id)
            ->whereIn('pieza', CatalogosCampana360::PIEZAS_VIDEO)
            ->whereIn('status', ['ready', 'completed', 'used'])
            ->first();

        $ruta = $video ? self::rutaSegura((string) $video->{$columna}) : null;

        if (! $ruta) {
            return Campana360Controller::negocio('no_encontrado', 'Ese video no está listo o ya no existe.', 404);
        }

        return response()->file($ruta, [
            'Content-Type'           => $tipo,
            'Cache-Control'          => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Ruta absoluta del archivo solo si existe y queda dentro de storage/app/marketing/videos. */
    public static function rutaSegura(string $relativa): ?string
    {
        if ($relativa === '' || str_contains($relativa, "\0")) {
            return null;
        }

        $base = realpath(storage_path('app/marketing/videos'));
        $ruta = realpath(storage_path('app/' . ltrim($relativa, '/')));

        if ($base === false || $ruta === false || ! is_file($ruta)) {
            return null;
        }

        return str_starts_with($ruta, $base . DIRECTORY_SEPARATOR) ? $ruta : null;
    }
}
