<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedFiber;
use App\Modules\Addons\MapaRed\Models\MapaRedFoto;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedProyect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Throwable;

/**
 * MR-23 fase 4c (item roadmap #9990455) — fotos adjuntas a nodos/enlaces del Mapa de Red
 * (tabla polimórfica mapared_fotos). Alcance y diseño ya cerrados por Irving (q1-q5 del item):
 * disco 'public', tabla única polimórfica, máx 5MB jpg/png/webp + thumbnail 300px, permisos
 * granulares ver/subir/eliminar, sección en el panel de detalle del elemento.
 *
 * `{tipo}` es una CLAVE corta (no el nombre de clase completo) resuelta contra un allowlist
 * fijo — nunca se acepta un `fotoable_type` arbitrario desde el cliente.
 */
class FotosController extends Controller
{
    private const TIPOS = [
        'layer' => MapaRedLayer::class,
        'device' => MapaRedDevice::class,
        'proyecto' => MapaRedProyect::class,
        'enlace' => MapaRedEnlaceServicio::class,
        'fiber' => MapaRedFiber::class,
    ];

    public function index(Request $request, string $tipo, int $id)
    {
        $this->authorize('mapa_red_fotos_ver');

        $modelo = $this->resolverModelo($tipo, $id);

        $fotos = MapaRedFoto::where('fotoable_type', get_class($modelo))
            ->where('fotoable_id', $modelo->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return response()->json($fotos);
    }

    public function store(Request $request, string $tipo, int $id)
    {
        $this->authorize('mapa_red_fotos_subir');

        $modelo = $this->resolverModelo($tipo, $id);

        $data = $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'caption' => 'nullable|string|max:255',
        ]);

        $carpeta = "mapa-red/{$tipo}/{$id}";
        $path = Storage::disk('public')->putFile($carpeta, $request->file('foto'));
        $thumbPath = $this->generarThumbnail($path, $carpeta);

        $maxOrden = MapaRedFoto::where('fotoable_type', get_class($modelo))
            ->where('fotoable_id', $modelo->id)
            ->max('orden');

        $foto = MapaRedFoto::create([
            'fotoable_type' => get_class($modelo),
            'fotoable_id' => $modelo->id,
            'path' => $path,
            'thumb_path' => $thumbPath,
            'caption' => $data['caption'] ?? null,
            'orden' => ($maxOrden ?? -1) + 1,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json($foto, 201);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorize('mapa_red_fotos_eliminar');

        $foto = MapaRedFoto::findOrFail($id);

        Storage::disk('public')->delete(array_filter([$foto->path, $foto->thumb_path]));

        $foto->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Best-effort: si Intervention Image falla (formato raro, memoria), la foto original
     * queda guardada de todas formas — solo se pierde el thumbnail (q3, opción 1).
     */
    private function generarThumbnail(string $path, string $carpeta): ?string
    {
        try {
            $thumbRelative = $carpeta . '/thumb_' . basename($path);
            Image::make(Storage::disk('public')->path($path))
                ->resize(300, 300, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->save(Storage::disk('public')->path($thumbRelative));

            return $thumbRelative;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function resolverModelo(string $tipo, int $id)
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404, 'Tipo de elemento no soportado para fotos.');

        return (self::TIPOS[$tipo])::findOrFail($id);
    }
}
