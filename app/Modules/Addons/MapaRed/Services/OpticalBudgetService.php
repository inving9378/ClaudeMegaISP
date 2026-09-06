<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\GestionRed\Models\OltOnu;
use App\Modules\Addons\GestionRed\Models\OltPonPort;
use App\Modules\Addons\MapaRed\Models\MapaRedEmpalme;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use App\Modules\Addons\MapaRed\Models\MapaRedSplitter;

/**
 * MR-18 (item roadmap #954) — presupuesto óptico automático desde el trazo.
 *
 * El trazo extremo a extremo "de verdad" (grafo dirigido, cacheado, con resaltado en el mapa)
 * es MR-16 (#952) — sigue sin construirse (escaló `requiere_irving` por timeout sin commits).
 * En vez de esperarlo o duplicar su alcance completo, `trazarRuta()` es un caminador INTERNO
 * mínimo, acotado solo a lo que este item necesita: caminar desde un `enlace_servicio` hacia
 * arriba (empalmes + cascada de splitters) hasta el puerto PON de la OLT, sumando metros de
 * cable y pérdidas. No expone API de grafo genérica, no cachea, no dibuja nada — si MR-16
 * aterriza después, puede reemplazar este método sin tocar `calcular()`.
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

    /**
     * Camina desde el enlace de servicio hacia la OLT, sumando segmentos.
     *
     * Tolerante a rutas incompletas (reporta hasta dónde llegó y por qué) y a ciclos (corta y
     * lo marca, nunca se cuelga) — mismo espíritu de robustez que pide el DoD de MR-16.
     */
    public function trazarRuta(MapaRedEnlaceServicio $enlace, int $maxSaltos = 64): array
    {
        $segmentos = [];
        $visitados = [];
        $completa = false;
        $motivoCorte = null;
        $puertoPon = null;

        $nodo = $this->nodoInicial($enlace, $segmentos);

        $saltos = 0;
        while ($nodo !== null) {
            if ($saltos++ >= $maxSaltos) {
                $motivoCorte = 'max_saltos_excedido';
                break;
            }

            $clave = $nodo['tipo'].':'.$nodo['id'];
            if (isset($visitados[$clave])) {
                $motivoCorte = 'ciclo_detectado';
                break;
            }
            $visitados[$clave] = true;

            if ($nodo['tipo'] === 'puerto') {
                $puerto = MapaRedPuerto::find($nodo['id']);
                if (! $puerto) {
                    $motivoCorte = 'puerto_inexistente';
                    break;
                }

                if ($puerto->rol === MapaRedPuerto::ROL_PON) {
                    $puertoPon = $puerto;
                    $completa = true;
                    break;
                }

                if ($puerto->puertable_type === MapaRedSplitter::class) {
                    $splitter = MapaRedSplitter::find($puerto->puertable_id);
                    if (! $splitter) {
                        $motivoCorte = 'splitter_inexistente';
                        break;
                    }

                    $segmentos[] = [
                        'tipo' => 'splitter',
                        'splitter_id' => $splitter->id,
                        'nivel' => $splitter->nivel,
                        'perdida_db' => $splitter->getPerdidaEfectivaDbAttribute(),
                    ];

                    $nodo = $this->siguienteTrasSplitter($splitter);
                    if ($nodo === null) {
                        $motivoCorte = 'splitter_sin_entrada_resuelta';
                    }

                    continue;
                }

                // Puerto pasivo sin pérdida modelada (NAP/ODF/splitter integrado sin fila
                // propia en mapared_splitters) — passthrough, seguir buscando el empalme
                // que lo alimenta.
                $nodo = $this->nodoDesdeEmpalmeQueApunta($puerto);
                if ($nodo === null) {
                    $motivoCorte = 'sin_empalme_hacia_el_puerto';
                }

                continue;
            }

            // $nodo['tipo'] === 'hilo'
            $hilo = MapaRedHilo::with('cable')->find($nodo['id']);
            if (! $hilo) {
                $motivoCorte = 'hilo_inexistente';
                break;
            }

            // `mapared_empalmes::hiloDisponible()` (MR-12/#948, ya mergeado) limita CADA hilo a
            // un único empalme activo, como hilo_a o como extremo_b. Si llegamos a este hilo
            // atravesando justo ese empalme, hay que EXCLUIRLO de la búsqueda: si no, la única
            // fila que existe es la misma por la que acabamos de entrar y el trazo rebotaría
            // hacia atrás (se leería como ciclo sin serlo). Sin más filas que esa, es un corte
            // real: el otro extremo del hilo no tiene más splices registrados.
            $empalme = $this->empalmeDelHilo($hilo->id, $nodo['via_empalme_id'] ?? null);
            if (! $empalme) {
                $motivoCorte = 'sin_empalme_saliente';
                break;
            }

            $esHiloA = (int) $empalme->hilo_a_id === (int) $hilo->id;
            $extremo = $esHiloA
                ? ['tipo' => $empalme->extremo_b_type, 'id' => $empalme->extremo_b_id]
                : ['tipo' => MapaRedHilo::class, 'id' => $empalme->hilo_a_id];

            $segmentos[] = [
                'tipo' => 'empalme',
                'empalme_id' => $empalme->id,
                'clase' => $empalme->tipo,
                'perdida_db' => (float) ($empalme->perdida_db ?? (MapaRedEmpalme::PERDIDA_DB_DEFAULT[$empalme->tipo] ?? 0)),
            ];

            $nodo = $extremo['tipo'] === MapaRedHilo::class
                ? $this->siguienteHilo((int) $extremo['id'], $empalme->id)
                : $this->siguientePuerto((int) $extremo['id']);
        }

        return [
            'completa' => $completa,
            'motivo_corte' => $motivoCorte,
            'segmentos' => $segmentos,
            'puerto_pon' => $puertoPon,
            'longitud_total_metros' => array_sum(array_column(
                array_filter($segmentos, fn ($s) => $s['tipo'] === 'cable'),
                'metros'
            )),
        ];
    }

    /**
     * Presupuesto óptico completo (DoD #954): desglose, total, comparación contra TX/sensibilidad
     * y contra el RX real que reporta MultiOLT.
     */
    public function calcular(MapaRedEnlaceServicio $enlace, string $ventana = self::VENTANA_DEFAULT): array
    {
        $ventana = array_key_exists($ventana, self::ATENUACION_DB_KM) ? $ventana : self::VENTANA_DEFAULT;
        $ruta = $this->trazarRuta($enlace);

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

    private function nodoInicial(MapaRedEnlaceServicio $enlace, array &$segmentos): ?array
    {
        if ($enlace->hilo_id) {
            $hilo = MapaRedHilo::with('cable')->find($enlace->hilo_id);
            if ($hilo) {
                if ($hilo->cable) {
                    $segmentos[] = [
                        'tipo' => 'cable',
                        'cable_id' => $hilo->cable_id,
                        'hilo_id' => $hilo->id,
                        'metros' => (float) $hilo->cable->longitud_metros,
                    ];
                }

                return $this->siguienteHilo($hilo->id);
            }
        }

        if ($enlace->puerto_nap_id) {
            return ['tipo' => 'puerto', 'id' => $enlace->puerto_nap_id];
        }

        return null;
    }

    private function siguienteHilo(int $hiloId, ?int $viaEmpalmeId = null): array
    {
        return ['tipo' => 'hilo', 'id' => $hiloId, 'via_empalme_id' => $viaEmpalmeId];
    }

    private function siguientePuerto(int $puertoId): array
    {
        return ['tipo' => 'puerto', 'id' => $puertoId];
    }

    /**
     * Empalme activo en el que participa el hilo, sea como `hilo_a_id` o como extremo B.
     * `$excluirId` descarta el empalme por el que ya se llegó a este hilo (ver el comentario en
     * `trazarRuta()`: un hilo solo puede tener UN empalme activo, así que sin la exclusión la
     * única fila que existiría sería la misma por la que se entró).
     */
    private function empalmeDelHilo(int $hiloId, ?int $excluirId = null): ?MapaRedEmpalme
    {
        return MapaRedEmpalme::query()
            ->where(function ($q) use ($hiloId) {
                $q->where('hilo_a_id', $hiloId)
                    ->orWhere(function ($q2) use ($hiloId) {
                        $q2->where('extremo_b_type', MapaRedHilo::class)->where('extremo_b_id', $hiloId);
                    });
            })
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->first();
    }

    /**
     * Empalme activo cuyo extremo B es este puerto (lo que lo alimenta desde aguas abajo del
     * cliente, es decir, aguas arriba en el sentido del trazo).
     */
    private function nodoDesdeEmpalmeQueApunta(MapaRedPuerto $puerto): ?array
    {
        $empalme = MapaRedEmpalme::query()
            ->where('extremo_b_type', MapaRedPuerto::class)
            ->where('extremo_b_id', $puerto->id)
            ->first();

        if (! $empalme) {
            return null;
        }

        return $this->siguienteHilo((int) $empalme->hilo_a_id, $empalme->id);
    }

    /**
     * Tras sumar la pérdida de un splitter, sigue la cascada: si es nivel 2, sube directo al
     * puerto de salida del padre (nivel 1) vía `puerto_entrada_padre_id` (FK, sin empalme de por
     * medio — así lo modeló MR-13); si es nivel 1, busca qué alimenta su propio puerto de entrada.
     */
    private function siguienteTrasSplitter(MapaRedSplitter $splitter): ?array
    {
        if ($splitter->nivel === MapaRedSplitter::NIVEL_2 && $splitter->puerto_entrada_padre_id) {
            return ['tipo' => 'puerto', 'id' => $splitter->puerto_entrada_padre_id];
        }

        $puertoEntrada = MapaRedPuerto::query()
            ->delDueno(MapaRedSplitter::class, $splitter->id)
            ->where('rol', MapaRedPuerto::ROL_SPLITTER_IN)
            ->first();

        if (! $puertoEntrada) {
            return null;
        }

        return $this->nodoDesdeEmpalmeQueApunta($puertoEntrada);
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
