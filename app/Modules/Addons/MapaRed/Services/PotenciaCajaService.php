<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Models\ClientMainInformation;
use App\Modules\Addons\GestionRed\Models\OltOnu;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePort;
use App\Services\OLTsService;
use Illuminate\Support\Facades\Log;

/**
 * Item #1235 — potencia por cliente y de la caja de servicio (NAP con clientes directo al splitter).
 *
 * "Potencia del cliente" = lo que RECIBE la ONU (bajada, olt_onus.signal_1490), decisión de Irving.
 * La ONU de un puerto se halla por olt_onus.client_id = client_main_information.client_id; si no
 * hay, el cliente va "sin ONU" y nunca se inventa un valor.
 */
class PotenciaCajaService
{
    /**
     * Decisión de Irving (2026-11-09): potencia de la caja = cliente más débil + 1 dB
     * (−22 → −21): la acometida y los conectores le restan al cliente, no a la caja.
     */
    public const MARGEN_CAJA_DB = 1.0;

    /** Tope de consultas en vivo por apertura (cuida el presupuesto de la API de SmartOLT). */
    private const MAX_LECTURAS_VIVO = 40;

    public function __construct(private ?OLTsService $olts = null)
    {
    }

    public function calcular(int $layerId, bool $enVivo = true): array
    {
        $puertos = MapaRedDevicePort::query()
            ->whereNotNull('client_id')
            ->whereIn('device_id', function ($q) use ($layerId) {
                $q->select('id')->from('mapared_devices')->where('layer_id', $layerId);
            })
            ->get();

        $clientes = ClientMainInformation::whereIn('id', $puertos->pluck('client_id')->unique())
            ->get(['id', 'client_id'])->keyBy('id');

        $consultas = 0;
        $filas = [];

        foreach ($puertos as $puerto) {
            $cmi = $clientes[$puerto->client_id] ?? null;
            $onu = $cmi ? $this->buscarOnu($cmi->client_id) : null;
            $enVivoFila = false;

            if ($onu && $enVivo && $onu->unique_external_id && $consultas < self::MAX_LECTURAS_VIVO) {
                $consultas++;
                $enVivoFila = $this->leerEnVivo($onu);
                $onu = $onu->fresh() ?? $onu;
            }

            $actual = $onu && $onu->signal_1490 !== null && is_numeric($onu->signal_1490)
                ? round((float) $onu->signal_1490, 2) : null;

            $inicial = $this->capturarInicial($puerto, $actual, $enVivoFila || !$enVivo);

            $filas[] = [
                'port_id' => $puerto->id,
                'client_id' => $puerto->client_id,
                'tiene_onu' => $onu !== null,
                'actual_dbm' => $actual,
                'inicial_dbm' => $inicial,
                'diferencia_db' => ($actual !== null && $inicial !== null) ? round($actual - $inicial, 2) : null,
                'en_vivo' => $enVivoFila,
                'leida_at' => $onu?->last_synced_at ? (string) $onu->last_synced_at : null,
            ];
        }

        $lecturas = array_filter(array_column($filas, 'actual_dbm'), fn ($v) => $v !== null);
        $minimo = $lecturas ? min($lecturas) : null;

        return [
            'potencia_caja_dbm' => $minimo !== null ? round($minimo + self::MARGEN_CAJA_DB, 2) : null,
            'cliente_mas_debil_dbm' => $minimo,
            'margen_db' => self::MARGEN_CAJA_DB,
            'con_lectura' => count($lecturas),
            'sin_onu' => count(array_filter($filas, fn ($f) => !$f['tiene_onu'])),
            'total_clientes' => count($filas),
            'clientes' => $filas,
        ];
    }

    /** Corrección manual de la toma inicial (mismo criterio que la potencia de instalación de la NAP). */
    public function corregirInicial(int $portId, ?float $dbm): MapaRedDevicePort
    {
        $puerto = MapaRedDevicePort::findOrFail($portId);
        $puerto->potencia_inicial_dbm = $dbm;
        $puerto->potencia_inicial_at = $dbm !== null ? now() : null;
        $puerto->save();

        return $puerto;
    }

    private function buscarOnu($clientId): ?OltOnu
    {
        if ($clientId === null || $clientId === '') {
            return null;
        }

        return OltOnu::where('client_id', $clientId)->orderByDesc('last_synced_at')->first();
    }

    private function leerEnVivo(OltOnu $onu): bool
    {
        try {
            $olts = $this->olts ?? app(OLTsService::class);
            $r = $olts->syncSignal($onu->unique_external_id);

            return (bool) ($r['success'] ?? false);
        } catch (\Throwable $e) {
            // Presupuesto agotado o API caída: se cae a la última lectura guardada.
            Log::info('[PotenciaCajaService] Sin lectura en vivo, uso la guardada', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /** La primera lectura real se guarda una sola vez y nunca se pisa sola. */
    private function capturarInicial(MapaRedDevicePort $puerto, ?float $actual, bool $permitir): ?float
    {
        if ($puerto->potencia_inicial_dbm === null && $actual !== null && $permitir) {
            try {
                $puerto->potencia_inicial_dbm = $actual;
                $puerto->potencia_inicial_at = now();
                $puerto->save();
            } catch (\Throwable $e) {
                Log::warning('[PotenciaCajaService] No se pudo guardar la toma inicial', ['port_id' => $puerto->id, 'error' => $e->getMessage()]);
            }
        }

        return $puerto->potencia_inicial_dbm !== null ? (float) $puerto->potencia_inicial_dbm : null;
    }
}
