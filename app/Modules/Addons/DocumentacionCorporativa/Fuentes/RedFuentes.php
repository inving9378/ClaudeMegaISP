<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Fuentes;

use App\Models\Network;
use App\Models\Olt;
use App\Models\OltCard;
use App\Models\OltOdb;
use App\Models\OltOnu;
use App\Models\OltPonPort;
use App\Models\OltZone;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;

/**
 * Fase 1.4 (item #731) — fuente viva de equipos de telecomunicaciones (red
 * OLT/ONU) para el Apartado V. Mismo mecanismo que Fase 1.1/1.2/1.3: solo
 * lectura, nunca escribe. `$empresaId` no se usa (una sola empresa
 * corporativa hoy).
 *
 * Decisión registrada (Fase 1.4, ver `circuito:reportar --tipo=decision`):
 * `OLTsService` (`app/Services/OLTsService.php`) es un cliente HTTP hacia la
 * API REMOTA de SmartOLT (`makeRequest()`), NO tiene ningún método que lea
 * las tablas locales `olts`/`olt_onus`/... — esos datos ya viven en BD porque
 * los comandos `syncOlts()`/`syncOnusStatus()`/etc del propio servicio los
 * escriben ahí de forma periódica (Kernel.php, cada 10m). Consultar los
 * modelos Eloquent locales (mismo patrón que `FinanzasFuentes`/
 * `TalentoFuentes`: query directa al modelo dueño de los datos) es más
 * rápido, no depende de que SmartOLT esté arriba en el momento de generar el
 * documento, y es justo lo que ya hace el resto del sistema para pintar estas
 * pantallas — no se duplica ninguna lógica de sync, solo se LEE lo que ya
 * está sincronizado.
 *
 * REGLA DE CREDENCIALES: `olt_onus` trae columnas sensibles del ONU
 * (`password` PPPoE, password de VoIP dentro de `voip_ports`) — un dump
 * "SELECT *" de las 2954 filas las expondría. Por eso `datos` NUNCA lista
 * ONUs individuales: es un censo agregado por OLT (3 filas), y ninguna
 * columna seleccionada aquí es una credencial.
 */
class RedFuentes
{
    public static function registrar(FuenteRegistry $registry): void
    {
        $registry->registrar('red.equipos', static fn (array $config, int $empresaId): array => self::equipos());
    }

    private static function equipos(): array
    {
        $olts = Olt::query()->orderBy('name')->get();

        $onusPorOlt    = OltOnu::query()->selectRaw('olt_id, COUNT(*) as total')->groupBy('olt_id')->pluck('total', 'olt_id');
        $onlinePorOlt  = OltOnu::query()->where('status', 'Online')->selectRaw('olt_id, COUNT(*) as total')->groupBy('olt_id')->pluck('total', 'olt_id');
        $cardsPorOlt   = OltCard::query()->selectRaw('olt_id, COUNT(*) as total')->groupBy('olt_id')->pluck('total', 'olt_id');
        $portsPorOlt   = OltPonPort::query()->selectRaw('olt_id, COUNT(*) as total')->groupBy('olt_id')->pluck('total', 'olt_id');

        $datos = $olts->map(fn (Olt $olt) => [
            'olt'            => $olt->name,
            'ip'             => $olt->ip,
            'estado'         => $olt->status,
            'uptime'         => $olt->uptime,
            'tarjetas'       => (int) ($cardsPorOlt[$olt->id] ?? 0),
            'puertos_pon'    => (int) ($portsPorOlt[$olt->id] ?? 0),
            'onus_total'     => (int) ($onusPorOlt[$olt->id] ?? 0),
            'onus_en_linea'  => (int) ($onlinePorOlt[$olt->id] ?? 0),
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_olts'     => $olts->count(),
                'total_onus'     => OltOnu::count(),
                'onus_en_linea'  => OltOnu::where('status', 'Online')->count(),
                'total_odbs'     => OltOdb::count(),
                'total_zonas'    => OltZone::count(),
                'total_networks' => Network::count(),
            ],
            'mensaje' => $datos === [] ? 'Sin OLTs registradas en este entorno.' : null,
        ];
    }
}
