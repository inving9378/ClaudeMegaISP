<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\GestionRed\Models\OltOnu;
use App\Modules\Addons\GestionRed\Models\OltPonPort;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;

/**
 * MR-18 (item roadmap #954) — presupuesto óptico automático desde el trazo.
 *
 * El caminador (grafo dirigido, cacheado) vive en `RedGraphService` desde MR-16 Fase 1
 * (item #9990468, extracción de lo que antes era `trazarRuta()` embebido aquí mismo). Este
 * servicio solo delega el trazo y convierte segmentos en pérdida óptica — no camina nada.
 *
 * D15 (ventanas ópticas + conector/fusión) solo vive documentado como texto en
 * `SembrarMapaRedCommand::tablaDecisiones()` — MR-08 (#944, catálogo `mapared_tipo_cable`) no
 * aterrizó, así que las constantes de ventana viven aquí como catálogo mínimo, igual que
 * `MapaRedEmpalme::PERDIDA_DB_DEFAULT` ya hizo para fusión/mecánico/conectorizado.
 */
class OpticalBudgetService
{
    /** D15: atenuación de fibra monomodo en dB/km según ventana óptica. */
    public const ATENUACION_DB_KM = [
        '1310' => 0.35,
        '1490' => 0.25,
        '1550' => 0.25,
    ];

    public const VENTANA_DEFAULT = '1490';

    /**
     * Sin catálogo de equipos (MR-08/#944 no aterrizó: no hay tabla de TX por modelo de OLT ni
     * sensibilidad por modelo de ONT). Defaults GPON Clase B+ — mismos umbrales que ya usa
     * `Talento\Services\CajaInspectionService` para lectura de campo (-8/-28 dBm), reusados aquí
     * para no inventar un catálogo paralelo.
     */
    public const TX_OLT_DBM_DEFAULT = 5.0;
    public const SENSIBILIDAD_ONT_MIN_DBM = -28.0;
    public const SENSIBILIDAD_ONT_MAX_DBM = -8.0;

    /** Tolerancia del DoD (#954): calculado vs. RX real de MultiOLT. */
    public const TOLERANCIA_DB = 3.0;

    public function __construct(private RedGraphService $redGraph)
    {
    }

    /**
     * Presupuesto óptico completo (DoD #954): desglose, total, comparación contra TX/sensibilidad
     * y contra el RX real que reporta MultiOLT. El trazo en sí (caminar desde el enlace hasta el
     * puerto PON) lo resuelve `RedGraphService::trazar()` (MR-16 Fase 1, #9990468) — este método
     * solo convierte esos segmentos en pérdida y arma el presupuesto.
     */
    public function calcular(MapaRedEnlaceServicio $enlace, string $ventana = self::VENTANA_DEFAULT): array
    {
        $ventana = array_key_exists($ventana, self::ATENUACION_DB_KM) ? $ventana : self::VENTANA_DEFAULT;
        $ruta = $this->redGraph->trazar($enlace);

        $desglose = [];
        $totalDb = 0.0;

        foreach ($ruta['segmentos'] as $segmento) {
            $perdida = match ($segmento['tipo']) {
                'cable' => round(((float) $segmento['metros'] / 1000) * self::ATENUACION_DB_KM[$ventana], 3),
                'empalme', 'splitter' => (float) $segmento['perdida_db'],
                default => 0.0,
            };

            $totalDb += $perdida;

            $desglose[] = [
                'elemento' => $segmento['tipo'],
                'detalle' => $segmento,
                'perdida_db' => $perdida,
            ];
        }

        $totalDb = round($totalDb, 3);

        $txOltReal = $this->txRealDeLaOlt($ruta['puerto_pon']);
        $txOlt = $txOltReal ?? self::TX_OLT_DBM_DEFAULT;
        $rxEstimadoDbm = round($txOlt - $totalDb, 3);
        $cierra = $rxEstimadoDbm >= self::SENSIBILIDAD_ONT_MIN_DBM && $rxEstimadoDbm <= self::SENSIBILIDAD_ONT_MAX_DBM;

        $rxRealDbm = $this->rxRealDeMultiOlt($enlace, $ventana);
        $diferenciaDb = $rxRealDbm !== null ? round(abs($rxEstimadoDbm - $rxRealDbm), 3) : null;

        return [
            'enlace_id' => $enlace->id,
            'ventana' => $ventana,
            'ruta_completa' => $ruta['completa'],
            'motivo_corte' => $ruta['motivo_corte'],
            'longitud_total_metros' => (float) $ruta['longitud_total_metros'],
            'desglose' => $desglose,
            'total_perdida_db' => $totalDb,
            'tx_olt_dbm' => $txOlt,
            'tx_olt_es_real' => $txOltReal !== null,
            'rx_estimado_dbm' => $rxEstimadoDbm,
            'sensibilidad_min_dbm' => self::SENSIBILIDAD_ONT_MIN_DBM,
            'sensibilidad_max_dbm' => self::SENSIBILIDAD_ONT_MAX_DBM,
            'cierra' => $cierra,
            'rx_real_dbm' => $rxRealDbm,
            'diferencia_db' => $diferenciaDb,
            'dentro_de_tolerancia' => $diferenciaDb !== null ? $diferenciaDb <= self::TOLERANCIA_DB : null,
        ];
    }

    /**
     * TX real de la OLT que sirve el puerto PON al que llegó el trazo (`OltPonPort.tx_power`,
     * sincronizado de MultiOLT). Sin FK real entre `mapared_puertos` (MapaRed, solo dibuja el
     * nodo en el mapa) y `olt_pon_ports` (GestionRed, la OLT física real) — no hay hoy manera de
     * saber A CUÁL OLT física corresponde el nodo del mapa. Emparejar solo por board/slot+puerto
     * podría acertarle a la OLT equivocada si dos OLTs comparten esa numeración (frecuente: casi
     * todas arrancan en board 0 puerto 0). Por eso solo se usa el valor si es INEQUÍVOCO (una
     * sola OLT en todo el sistema con ese board/puerto); si hay más de una candidata o ninguna,
     * cae a null y `calcular()` usa el default documentado.
     */
    private function txRealDeLaOlt(?MapaRedPuerto $puertoPon): ?float
    {
        if (! $puertoPon || ! $puertoPon->slot || ! $puertoPon->numero) {
            return null;
        }

        $candidatos = OltPonPort::query()
            ->where('board', $puertoPon->slot)
            ->where('pon_port', $puertoPon->numero)
            ->whereNotNull('tx_power')
            ->limit(2)
            ->get();

        return $candidatos->count() === 1 ? (float) $candidatos->first()->tx_power : null;
    }

    /**
     * RX real que reporta MultiOLT para la ONT de este enlace (D-something: `olt_onus.signal_*`),
     * emparejado por serie (`ont_serie` == `sn`) — el único identificador compartido entre
     * `mapared_enlaces_servicio` (que guarda cliente/ONT por nombre/serie, D19) y `olt_onus`.
     */
    private function rxRealDeMultiOlt(MapaRedEnlaceServicio $enlace, string $ventana): ?float
    {
        if (! $enlace->ont_serie) {
            return null;
        }

        $onu = OltOnu::query()->where('sn', $enlace->ont_serie)->first();
        if (! $onu) {
            return null;
        }

        $campo = $ventana === '1310' ? 'signal_1310' : 'signal_1490';
        $valor = $onu->{$campo};

        return $valor !== null && is_numeric($valor) ? (float) $valor : null;
    }
}
