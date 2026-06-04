<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * @param  int           $workOrderId
     * @param  string        $type
     * @param  float|null    $capturedLat   From app (trusted), NOT EXIF
     * @param  float|null    $capturedLng   From app (trusted), NOT EXIF
     * @param  string|null   $capturedAt    ISO datetime from app
     * @return TalentoWorkOrderMedia
     */
    public function store(
        UploadedFile $file,
        int          $workOrderId,
        string       $type,
        ?float       $capturedLat = null,
        ?float       $capturedLng = null,
        ?string      $capturedAt  = null
    ): TalentoWorkOrderMedia {
        $isSensitive = in_array($type, self::SENSITIVE_TYPES);
        $disk        = $isSensitive ? 'local' : 'local'; // private disk; serve via signed URL

        $dir  = "talento/media/{$workOrderId}";
        $ext  = $file->getClientOriginalExtension() ?: 'jpg';
        $name = Str::uuid() . '.' . $ext;
        $path = $file->storeAs($dir, $name, $disk);

        // Apply watermark if image and not sensitive doc
        $watermarkApplied = false;
        if (!$isSensitive && in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])) {
            $watermarkApplied = $this->applyWatermark(
                Storage::disk($disk)->path($path),
                $capturedLat, $capturedLng, $capturedAt
            );
        }

        // Check location proximity to order
        $locationFlagged  = false;
        $distanceM        = null;
        $order = TalentoWorkOrder::find($workOrderId);
        if ($order && $order->latitude && $order->longitude && $capturedLat && $capturedLng) {
            $distanceM = AttendanceService::haversineMeters(
                $capturedLat, $capturedLng,
                (float)$order->latitude, (float)$order->longitude
            );
            $locationFlagged = $distanceM > $this->flaggedRadius();
        }

        // Encrypt the stored path for sensitive documents (LFPDPPP)
        $storedPath = $isSensitive ? Crypt::encryptString($path) : $path;

        return TalentoWorkOrderMedia::create([
            'work_order_id'      => $workOrderId,
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
                default=> imagejpeg($img, $fullPath, 88),
            };
            imagedestroy($img);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
