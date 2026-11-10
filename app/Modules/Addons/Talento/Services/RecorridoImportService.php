<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoHallazgo;
use App\Modules\Addons\Talento\Models\TalentoHallazgoFoto;
use App\Modules\Addons\Talento\Models\TalentoRecorrido;
use App\Modules\Addons\Talento\Support\ExifFoto;
use App\Modules\Addons\Talento\Support\TrazaRecorrido;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use InvalidArgumentException;

/**
 * Recorridos, fase (a) — importar lo que se grabó con Geo Tracker (item #1311).
 *
 * - La ruta (GPX/KML/KMZ, o el GPX que exporta Flotas) se guarda tal cual como evidencia en el disco
 *   privado; en BD queda solo lo calculado (TrazaRecorrido). Recortar vuelve a leer el original.
 * - Cada foto crea un hallazgo. Su ubicación: la del EXIF; si no trae, la de la ruta a la hora de la
 *   foto; si tampoco, queda sin ubicar para ponerla a mano. La foto se guarda reducida
 *   (1600 px, calidad 80) después de leer el EXIF. La misma foto dos veces no se duplica.
 *
 * Los errores de datos salen como InvalidArgumentException con un mensaje para el usuario.
 */
class RecorridoImportService
{
    public const DISCO = 'local';

    public function crear(TalentoColaborador $colaborador, UploadedFile $archivo, array $datos, User $por): TalentoRecorrido
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $formato = TrazaRecorrido::formatoDe($contenido, $archivo->getClientOriginalName());
        if (! $formato) {
            throw new InvalidArgumentException('El archivo de ruta no es GPX, KML ni KMZ.');
        }
        $puntos = TrazaRecorrido::leer($contenido, $formato);
        if (count($puntos) < 2) {
            throw new InvalidArgumentException('El archivo de ruta no trae una ruta (necesita al menos dos puntos).');
        }

        $ruta = null;
        try {
            return DB::transaction(function () use ($colaborador, $archivo, $datos, $por, $contenido, $formato, $puntos, &$ruta) {
                $recorrido = TalentoRecorrido::create([
                    'colaborador_id' => $colaborador->id,
                    'tarea_id'       => $datos['tarea_id'] ?? null,
                    'titulo'         => $datos['titulo'] ?? null,
                    'modo'           => $datos['modo'],
                    'iniciado_por'   => (int) $colaborador->user_id === (int) $por->id ? 'colaborador' : 'supervisor',
                    'fuente'         => $formato,
                    'estado'         => 'borrador',
                    'archivo_nombre' => Str::limit($archivo->getClientOriginalName(), 250, ''),
                    'archivo_bytes'  => strlen($contenido),
                    'archivo_sha256' => hash('sha256', $contenido),
                    'puntos_total'   => count($puntos),
                    'muestra'        => TrazaRecorrido::muestra($puntos),
                    'created_by'     => $por->id,
                    'updated_by'     => $por->id,
                ]);

                $ruta = "talento/recorridos/{$recorrido->id}/original.{$formato}";
                Storage::disk(self::DISCO)->put($ruta, $contenido);
                $recorrido->archivo_ruta = $ruta;
                $this->aplicarTramo($recorrido, $puntos, null, null);
                $recorrido->save();

                return $recorrido;
            });
        } catch (\Throwable $e) {
            $ruta && Storage::disk(self::DISCO)->delete($ruta);
            throw $e;
        }
    }

    /** Puntos del archivo original (lo que se importó, sin recortar). */
    public function puntos(TalentoRecorrido $recorrido): array
    {
        $contenido = Storage::disk(self::DISCO)->get((string) $recorrido->archivo_ruta);
        if ($contenido === null) {
            throw new InvalidArgumentException('No se encontró el archivo de ruta original.');
        }

        return TrazaRecorrido::leer($contenido, $recorrido->fuente);
    }

    /** Recorta inicio y fin (índices sobre los puntos del original) y recalcula. */
    public function recortar(TalentoRecorrido $recorrido, int $desde, int $hasta, User $por): TalentoRecorrido
    {
        $puntos = $this->puntos($recorrido);
        $ultimo = count($puntos) - 1;
        if ($desde < 0 || $hasta > $ultimo || $hasta - $desde < 1) {
            throw new InvalidArgumentException('El recorte debe dejar al menos dos puntos de la ruta.');
        }
        $completo = $desde === 0 && $hasta === $ultimo;
        $this->aplicarTramo($recorrido, $puntos, $completo ? null : $desde, $completo ? null : $hasta);
        $recorrido->updated_by = $por->id;
        $recorrido->save();

        return $recorrido;
    }

    private function aplicarTramo(TalentoRecorrido $recorrido, array $puntos, ?int $desde, ?int $hasta): void
    {
        $tramo = array_slice($puntos, $desde ?? 0, ($hasta ?? count($puntos) - 1) - ($desde ?? 0) + 1);
        $horas = array_values(array_filter(array_column($tramo, 2), fn ($t) => $t !== null));
        $tolerancia = (float) (config('talento.recorridos.simplificar_m')[$recorrido->modo] ?? 5);

        $recorrido->fill([
            'recorte_desde' => $desde,
            'recorte_hasta' => $hasta,
            'puntos'        => count($tramo),
            'distancia_m'   => (int) round(TrazaRecorrido::distancia($tramo)),
            'geometria'     => TrazaRecorrido::simplificar($tramo, $tolerancia),
            'bbox'          => TrazaRecorrido::bbox($tramo),
            'iniciado_at'   => $horas ? Carbon::createFromTimestamp(min($horas)) : null,
            'terminado_at'  => $horas ? Carbon::createFromTimestamp(max($horas)) : null,
        ]);
    }

    /**
     * Agrega una foto como hallazgo nuevo.
     *
     * @return array{hallazgo: TalentoHallazgo, duplicada: bool}
     */
    public function agregarFoto(TalentoRecorrido $recorrido, UploadedFile $foto, User $por, ?array $puntos = null): array
    {
        $origen = $foto->getRealPath();
        $sha = hash_file('sha256', $origen);
        $repetida = TalentoHallazgoFoto::where('sha256', $sha)
            ->whereHas('hallazgo', fn ($q) => $q->where('recorrido_id', $recorrido->id))
            ->first();
        if ($repetida) {
            return ['hallazgo' => $repetida->hallazgo, 'duplicada' => true];
        }

        $exif = ExifFoto::leer($origen);
        [$jpg, $ancho, $alto] = $this->reducir($origen);

        [$lat, $lng, $fuente] = [$exif['lat'], $exif['lng'], $exif['lat'] !== null ? 'exif' : null];
        if ($fuente === null && $exif['tomada_at']) {
            $puntos ??= $this->puntos($recorrido);
            $sobreRuta = TrazaRecorrido::ubicarPorHora($puntos, $exif['tomada_at']->getTimestamp(), (int) config('talento.recorridos.tolerancia_hora_s', 300));
            if ($sobreRuta) {
                [$lat, $lng, $fuente] = [$sobreRuta[0], $sobreRuta[1], 'por_hora'];
            }
        }

        $ruta = "talento/recorridos/{$recorrido->id}/fotos/" . Str::uuid() . '.jpg';
        Storage::disk(self::DISCO)->put($ruta, $jpg);

        try {
            $hallazgo = DB::transaction(function () use ($recorrido, $foto, $por, $exif, $jpg, $ancho, $alto, $lat, $lng, $fuente, $ruta, $sha, $origen) {
                $hallazgo = TalentoHallazgo::create([
                    'recorrido_id'     => $recorrido->id,
                    'lat'              => $lat,
                    'lng'              => $lng,
                    'fuente_ubicacion' => $fuente,
                    'capturado_at'     => $exif['tomada_at'],
                    'orden'            => (int) TalentoHallazgo::where('recorrido_id', $recorrido->id)->withTrashed()->max('orden') + 1,
                    'estado'           => 'pendiente',
                    'created_by'       => $por->id,
                    'updated_by'       => $por->id,
                ]);
                TalentoHallazgoFoto::create([
                    'hallazgo_id'     => $hallazgo->id,
                    'ruta'            => $ruta,
                    'nombre_original' => Str::limit($foto->getClientOriginalName(), 250, ''),
                    'bytes'           => strlen($jpg),
                    'bytes_original'  => filesize($origen) ?: null,
                    'ancho'           => $ancho,
                    'alto'            => $alto,
                    'sha256'          => $sha,
                    'exif_lat'        => $exif['lat'],
                    'exif_lng'        => $exif['lng'],
                    'exif_tomada_at'  => $exif['tomada_at'],
                    'created_by'      => $por->id,
                    'updated_by'      => $por->id,
                ]);

                return $hallazgo;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISCO)->delete($ruta);
            throw $e;
        }

        return ['hallazgo' => $hallazgo, 'duplicada' => false];
    }

    /** @return array{0: string, 1: int, 2: int} JPEG reducido, ancho y alto. */
    private function reducir(string $ruta): array
    {
        $lado = (int) config('talento.recorridos.foto_lado_max', 1600);
        try {
            $img = Image::make($ruta)->orientate();
            if (max($img->width(), $img->height()) > $lado) {
                $img->resize($lado, $lado, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
            }
            $jpg = (string) $img->encode('jpg', (int) config('talento.recorridos.foto_calidad', 80));
            $medidas = [$img->width(), $img->height()];
            $img->destroy();
        } catch (\Throwable) {
            throw new InvalidArgumentException('No se pudo leer la foto; debe ser JPG, PNG o WebP.');
        }

        return [$jpg, ...$medidas];
    }

    /** Une el hallazgo $origen dentro de $destino: pasan sus fotos y lo que $destino no tenga. */
    public function unir(TalentoHallazgo $origen, TalentoHallazgo $destino, User $por): TalentoHallazgo
    {
        return DB::transaction(function () use ($origen, $destino, $por) {
            TalentoHallazgoFoto::where('hallazgo_id', $origen->id)->update(['hallazgo_id' => $destino->id, 'updated_by' => $por->id]);
            foreach (['categoria_id', 'severidad', 'lat', 'lng', 'fuente_ubicacion', 'precision_m', 'capturado_at'] as $campo) {
                if ($destino->{$campo} === null && $origen->{$campo} !== null) {
                    $destino->{$campo} = $origen->{$campo};
                }
            }
            $textos = array_filter([trim((string) $destino->descripcion), trim((string) $origen->descripcion)]);
            $destino->descripcion = $textos ? implode("\n", array_unique($textos)) : null;
            $destino->updated_by = $por->id;
            $destino->save();
            $origen->forceDelete();

            return $destino;
        });
    }

    /** Borra un hallazgo de un borrador junto con sus fotos (archivos incluidos). */
    public function eliminar(TalentoHallazgo $hallazgo): void
    {
        $rutas = $hallazgo->fotos()->pluck('ruta')->all();
        $hallazgo->forceDelete();
        Storage::disk(self::DISCO)->delete($rutas);
    }
}
