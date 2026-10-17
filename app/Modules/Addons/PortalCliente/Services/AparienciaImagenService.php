<?php

namespace App\Modules\Addons\PortalCliente\Services;

use App\Modules\Addons\PortalCliente\Models\AparienciaImagen;
use App\Modules\Addons\PortalCliente\Support\AparienciaCatalogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;

/**
 * Carga segura de imágenes de Configuración → Portal Cliente.
 *
 * Nunca se guarda el archivo tal como llega: se valida extensión, MIME real (finfo), que ambos
 * coincidan, que sea JPG/PNG/WebP decodificable, medidas mínimas y un tope de megapíxeles; luego
 * se RE-CODIFICA a WebP con un nombre generado por el servidor. Re-codificar descarta metadatos
 * (EXIF/GPS) y cualquier contenido incrustado (polyglots, scripts), así que lo servido es siempre
 * una imagen limpia. SVG y cualquier otro formato quedan fuera por diseño.
 */
class AparienciaImagenService
{
    private const MIME_POR_EXTENSION = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    private const TIPOS_GD = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function subir(UploadedFile $archivo, string $tipo, ?int $usuarioId): AparienciaImagen
    {
        if (! in_array($tipo, AparienciaCatalogo::TIPOS_IMAGEN, true)) {
            $this->rechazar('Tipo de imagen no válido.');
        }

        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        if (! isset(self::MIME_POR_EXTENSION[$extension])) {
            $this->rechazar('Solo se aceptan imágenes JPG, PNG o WebP.');
        }

        $origen = $archivo->getRealPath();
        $mimeReal = $origen ? (new \finfo(FILEINFO_MIME_TYPE))->file($origen) : null;
        if (! in_array($mimeReal, self::MIME_POR_EXTENSION, true)) {
            $this->rechazar('El contenido del archivo no es una imagen JPG, PNG o WebP.');
        }
        if ($mimeReal !== self::MIME_POR_EXTENSION[$extension]) {
            $this->rechazar('La extensión del archivo no coincide con su contenido.');
        }

        $info = @getimagesize($origen);
        if ($info === false || ! in_array($info[2], self::TIPOS_GD, true)) {
            $this->rechazar('No se pudo leer la imagen. Prueba con otro archivo.');
        }

        [$ancho, $alto] = $info;
        if ($ancho * $alto > (int) config('portalcliente.apariencia.pixeles_maximos', 36_000_000)) {
            $this->rechazar('La imagen es demasiado grande: el máximo es 36 megapíxeles.');
        }
        [$minAncho, $minAlto] = AparienciaCatalogo::MINIMOS[$tipo];
        if ($ancho < $minAncho || $alto < $minAlto) {
            $this->rechazar("La imagen debe medir al menos {$minAncho} × {$minAlto} px (la tuya mide {$ancho} × {$alto}).");
        }

        try {
            $imagen = (new ImageManager(['driver' => 'gd']))->make($origen);
            if ($info[2] === IMAGETYPE_JPEG) {
                $imagen->orientate();
            }
            [$maxAncho, $maxAlto] = AparienciaCatalogo::MAXIMOS_SALIDA[$tipo];
            $imagen->resize($maxAncho, $maxAlto, function ($c) {
                $c->aspectRatio();
                $c->upsize();
            });
            $calidad = $tipo === 'logo' ? 92 : (int) config('portalcliente.apariencia.calidad_webp', 82);
            $binario = (string) $imagen->encode('webp', $calidad);
            $imagen->destroy();
        } catch (\Throwable $e) {
            Log::warning('[PortalCliente/apariencia] No se pudo re-codificar la imagen: ' . $e->getMessage());
            $this->rechazar('No se pudo procesar la imagen. Prueba con otro archivo.');
        }

        $salida = @getimagesizefromstring($binario);
        if ($salida === false || ($salida['mime'] ?? null) !== 'image/webp') {
            $this->rechazar('No se pudo procesar la imagen. Prueba con otro archivo.');
        }

        $uuid = (string) Str::uuid();
        $disco = (string) config('portalcliente.apariencia.disco', 'local');
        $ruta = trim((string) config('portalcliente.apariencia.directorio', 'portal-cliente/apariencia'), '/') . "/{$uuid}.webp";
        Storage::disk($disco)->put($ruta, $binario);

        $nombre = preg_replace('/[^\pL\pN ._()-]/u', '', (string) $archivo->getClientOriginalName());

        return AparienciaImagen::create([
            'uuid'            => $uuid,
            'tipo'            => $tipo,
            'disco'           => $disco,
            'ruta'            => $ruta,
            'mime'            => 'image/webp',
            'peso'            => strlen($binario),
            'ancho'           => (int) $salida[0],
            'alto'            => (int) $salida[1],
            'sha256'          => hash('sha256', $binario),
            'nombre_original' => Str::limit((string) $nombre, 140, ''),
            'created_by'      => $usuarioId,
            'updated_by'      => $usuarioId,
        ]);
    }

    public function existeArchivo(AparienciaImagen $imagen): bool
    {
        return Storage::disk($imagen->disco)->exists($imagen->ruta);
    }

    /** Respuesta HTTP con la imagen. Pública = caché larga (el archivo de un uuid nunca cambia). */
    public function respuesta(AparienciaImagen $imagen, bool $publica)
    {
        abort_unless($this->existeArchivo($imagen), 404);

        return response()->file(Storage::disk($imagen->disco)->path($imagen->ruta), [
            'Content-Type'           => 'image/webp',
            'Cache-Control'          => $publica ? 'public, max-age=31536000, immutable' : 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => 'inline; filename="' . $imagen->uuid . '.webp"',
        ]);
    }

    public function eliminar(AparienciaImagen $imagen): void
    {
        Storage::disk($imagen->disco)->delete($imagen->ruta);
        $imagen->delete();
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['archivo' => $mensaje]);
    }
}
