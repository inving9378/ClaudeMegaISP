<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPendiente;
use App\Modules\Addons\DocumentacionCorporativa\Resolvers\ResolverFactory;
use Illuminate\Support\Carbon;

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

    /** Tira el caché del tablero: llamarlo tras subir/eliminar un documento (Fase 2a, item #767). */
    public function invalidar(int $empresaId): void
    {
        cache()->forget("dc:completitud:{$empresaId}");
    }

    /** Un solo apartado, siempre en vivo (son ~10 conceptos, no 139). */
    public function apartado(DcApartado $apartado, int $empresaId): array
    {
        $conceptos = $apartado->conceptos()->activos()->get();

        return $this->resumirConceptos($apartado, $conceptos, $empresaId);
    }

    /**
     * Agrega un conjunto de apartados en un solo global. Público porque el
     * controlador lo necesita para recalcular sobre lo que ESE usuario puede ver
     * — y si lo hiciera por su cuenta habría dos definiciones de "porcentaje" y
     * dos de "semáforo", que es exactamente como divergen.
     */
    public function agregarGlobal(array $apartados): array
    {
        $obligatorios = array_sum(array_column($apartados, 'obligatorios'));
        $resueltos    = array_sum(array_column($apartados, 'resueltos'));
        $porcentaje   = $this->porcentaje($resueltos, $obligatorios);
        $estados      = array_count_values(array_column($apartados, 'estado'));

        return [
            'obligatorios' => $obligatorios,
            'resueltos'    => $resueltos,
            'porcentaje'   => $porcentaje,
            'medible'      => $obligatorios > 0,
            'semaforo'     => $this->semaforo($porcentaje, $obligatorios),
            'apartados'    => count($apartados),
            // Fase B (item #9990531): conteo por estado para las tarjetas KPI y
            // los chips de filtro del tablero.
            'al_dia'         => $estados['al_dia'] ?? 0,
            'en_proceso'     => $estados['en_proceso'] ?? 0,
            'sin_iniciar'    => $estados['sin_iniciar'] ?? 0,
            // No existe en ningún lado la fecha de inicio del plazo de 180 días
            // hábiles de la solicitud de la mesa directiva (verificado #9990531,
            // grep sin resultados) — se deja en null a propósito en vez de
            // inventarla; el seguimiento para agregarla vive en un item aparte.
            'dias_restantes' => null,
        ];
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
            'apartados'    => $filas,
            'global'       => $this->agregarGlobal($filas),
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
                // Filas del resolvedor (ej. documentos de un concepto tipo
                // 'documento'): mismos datos que ya usa la exportación
                // CSV/PDF, expuestos aquí para que el detalle del apartado
                // los pinte sin una segunda llamada (Fase 2a, item #767).
                'datos'           => $resultado->datos,
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
            'medible'      => $obligatorios > 0,
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
            'semaforo'     => $this->semaforo($porcentaje, $obligatorios),
            // Fase B (item #9990531) — estado de 3 valores para el badge del
            // tablero, independiente del semáforo (que sigue coloreando la
            // barra de avance con sus umbrales de siempre, sin cambio).
            'estado'       => $this->estadoApartado($porcentaje, $obligatorios),
            'fecha_ultima_actualizacion' => optional($this->fechaUltimaActualizacion($conceptos->pluck('id')))->toDateTimeString(),
            'responsables' => $this->responsables($apartado, $empresaId),
            'conceptos'    => $detalle,
        ];
    }

    /**
     * Estado de 3 valores del apartado (decisión de Irving, item #9990531 q2):
     * sin_iniciar (0%) / en_proceso (1-99%) / al_dia (100%). Un apartado sin
     * conceptos obligatorios que medir se agrupa en sin_iniciar — no hay un
     * cuarto valor para ese caso, la vista ya lo distingue con `medible`.
     */
    private function estadoApartado(int $porcentaje, int $obligatorios): string
    {
        if ($obligatorios === 0) {
            return 'sin_iniciar';
        }

        return match (true) {
            $porcentaje >= 100 => 'al_dia',
            $porcentaje <= 0   => 'sin_iniciar',
            default            => 'en_proceso',
        };
    }

    /**
     * Última vez que se tocó algo de este apartado (documento subido o
     * pendiente creado/editado) — solo alimenta el orden "días sin movimiento"
     * de la vista Lista del tablero (Fase B, item #9990531). No hay columna de
     * auditoría dedicada; se deriva de las dos fuentes reales disponibles.
     */
    private function fechaUltimaActualizacion($conceptoIds): ?Carbon
    {
        if ($conceptoIds->isEmpty()) {
            return null;
        }

        $fechas = collect([
            DcDocumento::whereIn('concepto_id', $conceptoIds)->max('updated_at'),
            DcPendiente::whereIn('concepto_id', $conceptoIds)->max('updated_at'),
        ])->filter();

        return $fechas->isEmpty() ? null : Carbon::parse($fechas->max());
    }

    /**
     * Un apartado sin conceptos obligatorios NO es medible, y eso no es lo mismo
     * que estar completo.
     *
     * La primera versión devolvía 100 % ahí, con el argumento de que no hay nada
     * que exigir. El resultado real fue que el apartado IV —cartera, saldos,
     * proveedores, ingresos: justo lo que el consejo pidió— salía en VERDE con
     * cero datos dentro. Un tablero que dice "completo" sobre un apartado vacío
     * es peor que uno que dice "rojo": el rojo se revisa, el verde no.
     *
     * Ahora esos apartados devuelven 0 % con semáforo `gris` y `medible = false`,
     * y la interfaz pinta "—" en vez de un porcentaje. No cuentan para el global
     * (su denominador es cero de todos modos).
     */
    private function porcentaje(int $resueltos, int $obligatorios): int
    {
        if ($obligatorios === 0) {
            return 0;
        }

        return (int) round($resueltos / $obligatorios * 100);
    }

    private function semaforo(int $porcentaje, ?int $obligatorios = null): string
    {
        if ($obligatorios === 0) {
            return 'gris';
        }

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
