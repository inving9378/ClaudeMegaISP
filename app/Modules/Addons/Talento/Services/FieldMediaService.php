<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class FieldMediaService
{
    private const SENSITIVE_TYPES = ['ine_front', 'ine_back', 'proof_address'];

    private function flaggedRadius(): float
    {
        return (float) (DB::table('settings')->where('key', 'talento_media_flagged_radius_m')->value('value') ?? 500);
    }

    /**
     * Store uploaded evidence photo with optional watermark.
     * The physical path written to DB is the real storage path.
     * For sensitive types the path itself is encrypted at rest using Laravel's Crypt.
     *
     * @param  UploadedFile  $file
     * @param  string        $origen        'work_order' | 'task' (ver FieldFlowEntity::resolve)
     * @param  int           $entityId      id de talento_work_orders o de tasks, según $origen
     * @param  string        $type
     * @param  float|null    $capturedLat   From app (trusted), NOT EXIF
     * @param  float|null    $capturedLng   From app (trusted), NOT EXIF
     * @param  string|null   $capturedAt    ISO datetime from app
     * @return TalentoWorkOrderMedia
     */
    public function store(
        UploadedFile $file,
        string       $origen,
        int          $entityId,
        string       $type,
        ?float       $capturedLat = null,
        ?float       $capturedLng = null,
        ?string      $capturedAt  = null
    ): TalentoWorkOrderMedia {
        $isSensitive = in_array($type, self::SENSITIVE_TYPES);
        $disk        = $isSensitive ? 'local' : 'local'; // private disk; serve via signed URL
        $isTask      = $origen === 'task';

        // Carpeta distinta para tasks: work_order_id y tarea_id son secuencias
        // de ids independientes (mismo patrón que SignatureService::store()),
        // así una OT #12 y una tarea #12 nunca comparten directorio.
        $folder = $isTask ? "tarea_{$entityId}" : $entityId;
        $dir  = "talento/media/{$folder}";
        $ext  = $file->getClientOriginalExtension() ?: 'jpg';
        $name = Str::uuid() . '.' . $ext;
        $path = $file->storeAs($dir, $name, $disk);

        // heic/heif → jpg, best-effort (David, 30-sep: "que se conviertan
        // automáticamente para que sí lleven el sello"). GD no puede leer
        // heic/heif directo; heif-convert (paquete Debian
        // libheif-examples) sí. Si el binario no está instalado en este
        // servidor, sigue exactamente como antes: se guarda el .heic/.heif
        // original, sin watermark (GD no lo puede decodificar), pero SIN
        // fallar la subida — es un best-effort, no un requisito.
        if (in_array(strtolower($ext), ['heic', 'heif'])) {
            $jpgPath = $this->convertHeicToJpeg($disk, $path);
            if ($jpgPath) {
                Storage::disk($disk)->delete($path);
                $path = $jpgPath;
                $ext  = 'jpg';
            }
        }

        // Apply watermark if image and not sensitive doc. avif ya lo
        // decodifica GD nativo en este servidor (PHP 8.2 con libavif);
        // heic/heif que no se pudieron convertir arriba quedan sin
        // watermark visible. La marca real contra fraude (GPS/timestamp en
        // captured_lat/captured_lng/captured_at, más abajo) no depende del
        // watermark y se registra siempre, sin importar el formato.
        $watermarkApplied = false;
        if (!$isSensitive && in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
            $watermarkApplied = $this->applyWatermark(
                Storage::disk($disk)->path($path),
                $capturedLat, $capturedLng, $capturedAt
            );
        }

        // Check location proximity to order — solo aplica a work_order: tasks
        // no tienen columnas latitude/longitude propias, así que para ellas
        // esto queda deshabilitado (no hay contra qué comparar).
        $locationFlagged  = false;
        $distanceM        = null;
        if (! $isTask) {
            $order = TalentoWorkOrder::find($entityId);
            if ($order && $order->latitude && $order->longitude && $capturedLat && $capturedLng) {
                $distanceM = AttendanceService::haversineMeters(
                    $capturedLat, $capturedLng,
                    (float)$order->latitude, (float)$order->longitude
                );
                $locationFlagged = $distanceM > $this->flaggedRadius();
            }
        }

        // Encrypt the stored path for sensitive documents (LFPDPPP)
        $storedPath = $isSensitive ? Crypt::encryptString($path) : $path;

        $fkCol = $isTask ? 'tarea_id' : 'work_order_id';

        return TalentoWorkOrderMedia::create([
            $fkCol                => $entityId,
            'type'               => $type,
            'file_path'          => $storedPath,
            'captured_lat'       => $capturedLat,
            'captured_lng'       => $capturedLng,
            'captured_at'        => $capturedAt ? \Carbon\Carbon::parse($capturedAt) : null,
            'captured_in_app'    => true,
            'watermark_applied'  => $watermarkApplied,
            'location_flagged'   => $locationFlagged,
            'location_distance_m'=> $distanceM !== null ? round($distanceM, 1) : null,
            'created_by'         => auth()->id(),
        ]);
    }

    /**
     * Resolve the real disk path for a media record.
     * For sensitive types, decrypts the stored path.
     */
    public function resolvePath(TalentoWorkOrderMedia $media): string
    {
        if ($media->isSensitive()) {
            return Crypt::decryptString($media->file_path);
        }
        return $media->file_path;
    }

    /**
     * Convierte un heic/heif ya guardado a jpg usando el binario `heif-convert`
     * (paquete Debian libheif-examples). Best-effort: si el binario no está
     * instalado o la conversión falla, devuelve null y el llamador conserva
     * el archivo original tal cual (sin watermark, pero sin fallar la subida).
     * Devuelve el path RELATIVO (al disco) del .jpg resultante, o null.
     */
    private function convertHeicToJpeg(string $disk, string $relativeSrcPath): ?string
    {
        $binary = '/usr/bin/heif-convert';
        if (! is_executable($binary)) {
            return null;
        }

        $srcFullPath = Storage::disk($disk)->path($relativeSrcPath);
        if (! file_exists($srcFullPath)) {
            return null;
        }

        $relativeDstPath = preg_replace('/\.(heic|heif)$/i', '.jpg', $relativeSrcPath);
        $dstFullPath     = Storage::disk($disk)->path($relativeDstPath);

        try {
            $process = new Process([$binary, $srcFullPath, $dstFullPath]);
            $process->setTimeout(20);
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return ($process->isSuccessful() && file_exists($dstFullPath)) ? $relativeDstPath : null;
    }

    /**
     * Burn a visible watermark onto the image with GPS coords and timestamp.
     * Returns true if successful, false if GD not available or failed.
     */
    private function applyWatermark(string $fullPath, ?float $lat, ?float $lng, ?string $capturedAt): bool
    {
        if (!extension_loaded('gd') || !file_exists($fullPath)) {
            return false;
        }

        try {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $img = match($ext) {
                'png'  => @imagecreatefrompng($fullPath),
                'webp' => @imagecreatefromwebp($fullPath),
                'avif' => @imagecreatefromavif($fullPath),
                default=> @imagecreatefromjpeg($fullPath),
            };
            if (!$img) return false;

            $w = imagesx($img);
            $h = imagesy($img);

            // Semi-transparent black bar at bottom
            $overlay = imagecreatetruecolor($w, 36);
            imagesavealpha($overlay, true);
            imagefill($overlay, 0, 0, imagecolorallocatealpha($overlay, 0, 0, 0, 60));
            imagecopymerge($img, $overlay, 0, $h - 36, 0, 0, $w, 36, 55);
            imagedestroy($overlay);

            $white = imagecolorallocate($img, 255, 255, 255);
            $ts    = $capturedAt ? \Carbon\Carbon::parse($capturedAt)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s');
            $gps   = ($lat && $lng) ? round($lat, 5) . ', ' . round($lng, 5) : '—';
            $text  = "Meganet ISP · {$ts} · {$gps}";

            imagestring($img, 3, 10, $h - 28, $text, $white);

            match($ext) {
                'png'  => imagepng($img, $fullPath),
                'webp' => imagewebp($img, $fullPath, 88),
                'avif' => imageavif($img, $fullPath, 80),
                default=> imagejpeg($img, $fullPath, 88),
            };
            imagedestroy($img);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
