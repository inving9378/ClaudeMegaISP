<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPendiente;
use App\Modules\Addons\DocumentacionCorporativa\Resolvers\ResolverFactory;

/**
 * Qué tan completo está el expediente.
 *
 * FÓRMULA (una sola, aquí): por apartado, conceptos resueltos ÷ conceptos
 * activos OBLIGATORIOS. Un concepto cuenta como resuelto cuando su resolvedor lo
 * dice — se le pregunta al resolvedor en vez de reimplementar la regla, porque
 * dos copias de "qué cuenta como resuelto" divergen el día que alguien toque una.
 *
 * En vivo en la vista de apartado; cacheado 15 minutos en el tablero general,
 * donde se recorren los 139 conceptos.
 */
class CompletitudService
{
    public const TTL_CACHE_SEGUNDOS = 900;

    public function __construct(private ResolverFactory $factory)
    {
    }

    /** Tablero general: los 14 apartados con su porcentaje y semáforo. Cacheado. */
    public function tablero(int $empresaId, bool $refrescar = false): array
    {
        $clave = "dc:completitud:{$empresaId}";

        if ($refrescar) {
            cache()->forget($clave);
        }

        return cache()->remember(
            $clave,
            self::TTL_CACHE_SEGUNDOS,
            fn () => $this->calcularTablero($empresaId)
        );
    }

    /** Un solo apartado, siempre en vivo (son ~10 conceptos, no 139). */
    public function apartado(DcApartado $apartado, int $empresaId): array
    {
        $conceptos = $apartado->conceptos()->activos()->get();

        return $this->resumirConceptos($apartado, $conceptos, $empresaId);
    }

    private function calcularTablero(int $empresaId): array
    {
        $apartados = DcApartado::deEmpresa($empresaId)->activos()
            ->with(['conceptos' => fn ($q) => $q->activos()])
            ->orderBy('orden')
            ->get();

        $filas          = [];
        $totalObligat   = 0;
        $totalResueltos = 0;

        foreach ($apartados as $apartado) {
            $fila = $this->resumirConceptos($apartado, $apartado->conceptos, $empresaId);

            $totalObligat   += $fila['obligatorios'];
            $totalResueltos += $fila['resueltos'];

            unset($fila['conceptos']);
            $filas[] = $fila;
        }

        return [
            'apartados' => $filas,
            'global'    => [
                'obligatorios' => $totalObligat,
                'resueltos'    => $totalResueltos,
                'porcentaje'   => $this->porcentaje($totalResueltos, $totalObligat),
                'semaforo'     => $this->semaforo($this->porcentaje($totalResueltos, $totalObligat)),
            ],
            'calculado_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DcConcepto>  $conceptos
     */
    private function resumirConceptos(DcApartado $apartado, $conceptos, int $empresaId): array
    {
        $obligatorios = 0;
        $resueltos    = 0;
        $faltantes    = [];
        $detalle      = [];

        foreach ($conceptos as $concepto) {
            $resolver  = $this->factory->para($concepto, $empresaId);
            $resultado = $resolver->resolver($concepto, $empresaId);
            $cuenta    = $resultado->cuentaComoResuelto();

            $detalle[] = [
                'id'              => $concepto->id,
                'nombre'          => $concepto->nombre,
                'slug'            => $concepto->slug,
                'tipo_resolvedor' => $concepto->tipo_resolvedor,
                'obligatorio'     => (bool) $concepto->obligatorio,
                'confidencialidad' => $concepto->confidencialidad,
                'periodicidad'    => $concepto->periodicidad_revision,
                'base_legal'      => $concepto->base_legal,
                'estado'          => $resultado->estado,
                'vista'           => $resultado->vista,
                'mensaje'         => $resultado->mensaje,
                'resuelto'        => $cuenta,
                'metricas'        => $resultado->metricas,
            ];

            if (! $concepto->obligatorio) {
                continue;
            }

            $obligatorios++;

            if ($cuenta) {
                $resueltos++;
            } else {
                // "Faltante" = obligatorio sin resolver. El tablero lo pinta en rojo.
                $faltantes[] = $concepto->nombre;
            }
        }

        $porcentaje = $this->porcentaje($resueltos, $obligatorios);

        return [
            'apartado_id'  => $apartado->id,
            'clave'        => $apartado->clave,
            'nombre'       => $apartado->nombre,
            'descripcion'  => $apartado->descripcion,
            'icono'        => $apartado->icono,
            'permiso'      => $apartado->permiso(),
            'conceptos_total' => count($detalle),
            'obligatorios' => $obligatorios,
            'resueltos'    => $resueltos,
            'faltantes'    => $faltantes,
            'porcentaje'   => $porcentaje,
            'semaforo'     => $this->semaforo($porcentaje),
            'responsables' => $this->responsables($apartado, $empresaId),
            'conceptos'    => $detalle,
        ];
    }

    /**
     * Un apartado sin conceptos obligatorios está completo por definición: no hay
     * nada que exigir. Devolver 0 % ahí lo pintaría en rojo para siempre.
     */
    private function porcentaje(int $resueltos, int $obligatorios): int
    {
        if ($obligatorios === 0) {
            return 100;
        }

        return (int) round($resueltos / $obligatorios * 100);
    }

    private function semaforo(int $porcentaje): string
    {
        return match (true) {
            $porcentaje >= 100 => 'verde',
            $porcentaje >= 34  => 'amarillo',
            default            => 'rojo',
        };
    }

    /** Quién responde por lo que falta en este apartado. */
    private function responsables(DcApartado $apartado, int $empresaId): array
    {
        $pendientes = DcPendiente::deEmpresa($empresaId)
            ->abiertos()
            ->whereIn('concepto_id', $apartado->conceptos()->activos()->pluck('id'))
            ->with('responsable')
            ->get();

        $nombres = $pendientes
            ->map(fn (DcPendiente $p) => $p->responsable?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'nombres'         => $nombres,
            'pendientes'      => $pendientes->count(),
            'sin_responsable' => $pendientes->whereNull('responsable_user_id')->count(),
        ];
    }
}
