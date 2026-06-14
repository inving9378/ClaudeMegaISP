<?php

namespace App\Modules\Addons\GestionRed\Controllers\OLTs;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints de geodatos para el mapa de red OLT.
 * Solo lectura — no escribe a ninguna tabla ni OLT.
 *
 * Tier de señal (usa campo categórico de SmartOLT):
 *   verde  → signal = "Very good"
 *   ambar  → signal = "Warning"
 *   rojo   → signal = "Critical"
 *   gris   → signal = "-" / NULL / status = "Offline"
 */
class OltGeoController extends Controller
{
    // Tiers documentados
    const TIER_MAP = [
        'Very good' => 'verde',
        'Warning'   => 'ambar',
        'Critical'  => 'rojo',
    ];

    /**
     * GET /olts/geo-capas
     * Retorna OLTs (sin geo), ODBs y ONUs con coordenadas válidas.
     */
    public function getCapas(): JsonResponse
    {
        return response()->json([
            'olts' => $this->getOlts(),
            'odbs' => $this->getOdbs(),
            'onus' => $this->getOnus(),
            'stats' => $this->getStats(),
        ]);
    }

    private function getOlts(): array
    {
        return DB::table('olts')
            ->select('id', 'name', 'ip', 'status', 'driver')
            ->orderBy('name')
            ->get()
            ->map(fn($o) => [
                'id'     => $o->id,
                'name'   => $o->name,
                'ip'     => $o->ip,
                'status' => $o->status,
                'driver' => $o->driver,
                // OLTs no tienen coordenadas en BD — se listan sin geo
                'lat'    => null,
                'lng'    => null,
            ])
            ->values()
            ->toArray();
    }

    private function getOdbs(): array
    {
        return DB::table('olt_odbs')
            ->where('latitude', '!=', '')
            ->whereNotNull('latitude')
            ->where('longitude', '!=', '')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'latitude', 'longitude', 'zone_name', 'last_synced_at')
            ->get()
            ->map(fn($o) => [
                'id'        => $o->id,
                'name'      => $o->name,
                'lat'       => (float) $o->latitude,
                'lng'       => (float) $o->longitude,
                'zone_name' => $o->zone_name,
            ])
            ->filter(fn($o) => $o['lat'] !== 0.0 && $o['lng'] !== 0.0)
            ->values()
            ->toArray();
    }

    private function getOnus(): array
    {
        return DB::table('olt_onus')
            ->where('latitude', '!=', '')
            ->whereNotNull('latitude')
            ->where('longitude', '!=', '')
            ->whereNotNull('longitude')
            ->select(
                'id', 'sn', 'name', 'latitude', 'longitude',
                'signal', 'signal_1490', 'status', 'olt_name', 'zone_name',
                'client_id', 'address'
            )
            ->get()
            ->map(fn($o) => [
                'id'         => $o->id,
                'sn'         => $o->sn,
                'name'       => $o->name ?: $o->sn,
                'lat'        => (float) $o->latitude,
                'lng'        => (float) $o->longitude,
                'signal'     => $o->signal,
                'signal_dbm' => $o->signal_1490 ? (float) $o->signal_1490 : null,
                'status'     => $o->status,
                'olt_name'   => $o->olt_name,
                'zone_name'  => $o->zone_name,
                'tier'       => $this->signalTier($o->signal, $o->status),
            ])
            ->filter(fn($o) => $o['lat'] !== 0.0 && $o['lng'] !== 0.0)
            ->values()
            ->toArray();
    }

    private function getStats(): array
    {
        return [
            'total_olts'  => DB::table('olts')->count(),
            'total_odbs'  => DB::table('olt_odbs')->count(),
            'odbs_con_geo' => DB::table('olt_odbs')->where('latitude', '!=', '')->whereNotNull('latitude')->count(),
            'total_onus'  => DB::table('olt_onus')->count(),
            'onus_con_geo' => DB::table('olt_onus')->where('latitude', '!=', '')->whereNotNull('latitude')->count(),
            'onus_online' => DB::table('olt_onus')->where('status', 'Online')->count(),
            'onus_offline' => DB::table('olt_onus')->where('status', 'Offline')->count(),
        ];
    }

    private function signalTier(?string $signal, ?string $status): string
    {
        if ($status === 'Offline') {
            return 'gris';
        }
        return self::TIER_MAP[$signal] ?? 'gris';
    }
}
