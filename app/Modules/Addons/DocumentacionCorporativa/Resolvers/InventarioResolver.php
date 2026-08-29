<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcInventarioAcceso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conceptos que se levantan a mano dentro del propio módulo: activos digitales,
 * dominios, licencias, inventario de accesos. Leen de una tabla `dc_*` que el
 * concepto declara en `config.tabla`.
 *
 * Las tablas de inventario nacen en la Fase 3. Mientras no existan, este
 * resolvedor se declara NO disponible y el concepto cae a `PendienteResolver`,
 * que dice la verdad: "sin fuente configurada".
 *
 * REGLA DE CREDENCIALES: ninguna de esas tablas lleva columna de secreto. El
 * campo de credencial se rinde como constante (`********`) más su leyenda; aquí
 * no hay nada que leer ni que ocultar porque el valor no existe en la base.
 */
class InventarioResolver extends BaseResolver
{
    /** Tablas `dc_*` que este resolvedor tiene permitido leer. */
    protected const TABLAS_PERMITIDAS = [
        'dc_activos',
        'dc_activos_digitales',
        'dc_inventario_accesos',
        'dc_concesiones',
        'dc_accionistas',
        'dc_capital_variaciones',
        'dc_actas',
        'dc_poderes',
        'dc_contratos',
        'dc_solicitudes',
        'dc_entregas',
        'dc_accesos_log',
    ];

    /**
     * Memo por request de `Schema::hasTable`: el tablero de completitud pregunta
     * por las mismas ~12 tablas una vez por concepto (139 veces), y cada llamada
     * golpea `information_schema`.
     *
     * @var array<string, bool>
     */
    private array $existe = [];

    public function disponible(DcConcepto $concepto, int $empresaId): bool
    {
        $tabla = $concepto->config['tabla'] ?? null;

        if ($tabla === null || ! in_array($tabla, self::TABLAS_PERMITIDAS, true)) {
            return false;
        }

        return $this->existe[$tabla] ??= Schema::hasTable($tabla);
    }

    public function resolver(DcConcepto $concepto, int $empresaId): ResultadoConcepto
    {
        $tabla    = $concepto->config['tabla'] ?? null;
        $metricas = [
            'obligatorio' => (bool) $concepto->obligatorio,
            'tabla'       => $tabla,
            // Filtros declarativos del concepto (p.ej. {tipo: 'consejo'}): el
            // frontend los usa para saber con qué tabla/filtro administrar
            // los registros de ESTE concepto sin tener que adivinarlo.
            'filtros'     => $concepto->config['filtros'] ?? [],
            // Fase 3.3 (item #752): `config.mapa=true` marca los conceptos de
            // dc_activos que además de la lista deben ofrecer un mapa Leaflet
            // (torres, postería, fibra, redes troncales, centros de
            // distribución, almacenes y bodegas).
            'mapa'        => (bool) ($concepto->config['mapa'] ?? false),
        ];

        if (! $this->disponible($concepto, $empresaId)) {
            return ResultadoConcepto::sinFuente(
                'Este concepto se inventaría en la tabla "' . ($tabla ?? 'sin declarar')
                . '", que todavía no existe. Se crea en la fase correspondiente.',
                $metricas
            );
        }

        // `dc_inventario_accesos` pasa por el modelo Eloquent, no por DB::table:
        // es la única tabla de este resolvedor con atributos CALCULADOS
        // (`credencial`, `credencial_leyenda` — ver DcInventarioAcceso). Leerla
        // con DB::table devolvería solo columnas físicas y la leyenda de la
        // regla de credenciales nunca llegaría a la tabla ni a sus
        // exportaciones (PDF/Excel/CSV), que es justo donde la solicitud exige
        // que viaje siempre.
        if ($tabla === 'dc_inventario_accesos') {
            $filas = $this->filasInventarioAccesos($concepto, $empresaId);
        } else {
            $query = DB::table($tabla)->where('empresa_id', $empresaId);

            if (Schema::hasColumn($tabla, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            // Filtros declarativos del concepto: `config.filtros` = {columna: valor}.
            foreach (($concepto->config['filtros'] ?? []) as $columna => $valor) {
                if (Schema::hasColumn($tabla, (string) $columna)) {
                    is_array($valor)
                        ? $query->whereIn($columna, $valor)
                        : $query->where($columna, $valor);
                }
            }

            $filas = $query->limit(500)->get()->map(fn ($f) => (array) $f)->all();
        }

        $metricas['registros'] = count($filas);

        if ($filas === []) {
            return ResultadoConcepto::vacio(
                'dc-concepto-tabla',
                'Sin registros en este entorno.',
                $metricas
            );
        }

        return ResultadoConcepto::resuelto('dc-concepto-tabla', $filas, $metricas);
    }

    /** @return array<int, array<string, mixed>> */
    private function filasInventarioAccesos(DcConcepto $concepto, int $empresaId): array
    {
        $query = DcInventarioAcceso::deEmpresa($empresaId)->with('custodio:id,name');

        foreach (($concepto->config['filtros'] ?? []) as $columna => $valor) {
            if (Schema::hasColumn('dc_inventario_accesos', (string) $columna)) {
                is_array($valor)
                    ? $query->whereIn($columna, $valor)
                    : $query->where($columna, $valor);
            }
        }

        return $query->limit(500)->get()
            ->map(fn (DcInventarioAcceso $fila) => $fila->toArray())
            ->all();
    }
}
