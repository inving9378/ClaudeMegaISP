<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumoController extends Controller
{
    /**
     * Consumo y velocidad de internet del cliente (item roadmap #493).
     *
     * Identidad de red: `client_internet_services.client_name` es el vínculo real y
     * auditable cliente→PPPoE (por client_id). Se complementa (no se reemplaza) con el
     * patrón legado 'Meganet{id}'/'Meganet__{id}' por si algún cliente activo no tiene
     * fila en client_internet_services pero sí trae datos históricos con ese patrón.
     * Aislamiento: los nombres se derivan siempre del client_id del cliente autenticado.
     *
     * Fuente de datos: solo lectura sobre tablas ya alimentadas por el job periódico
     * `mikrotik:sync-consumption` (cada 10min, ver Kernel.php) — sin consultas en vivo
     * al router desde esta pantalla.
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $servicios = DB::table('client_internet_services')
            ->where('client_id', $clientId)
            ->orderByRaw("estado IN ('Activo','Activado') DESC")
            ->orderByDesc('id')
            ->get(['client_name', 'user', 'internet_id', 'estado']);

        $names = $servicios->pluck('client_name')->filter()->values()->all();
        $names = array_unique(array_merge($names, ['Meganet' . $clientId, 'Meganet__' . $clientId]));

        // Velocidad contratada: del servicio de internet activo más reciente (si hay varios).
        $plan = null;
        $internetId = optional($servicios->first(fn ($s) => in_array($s->estado, ['Activo', 'Activado'], true)) ?? $servicios->first())->internet_id;
        if ($internetId) {
            $plan = DB::table('internets')->where('id', $internetId)
                ->first(['title', 'download_speed', 'upload_speed']);
            if ($plan && (int) $plan->download_speed <= 0 && (int) $plan->upload_speed <= 0) {
                $plan = null; // Plan sin velocidad configurada: no mostrar dato engañoso.
            }
        }

        // Actividad más reciente reportada por el router (uptime/IP/velocidad promedio del intervalo).
        $actividad = DB::table('internet_consumptions')
            ->whereIn('client_name', $names)
            ->orderByDesc('updated_at')
            ->first(['client_name', 'ip_address', 'uptime', 'rate_in_bps', 'rate_out_bps', 'updated_at']);

        $hoy = now()->toDateString();
        $consumoHoy = DB::table('daily_internet_consumptions')
            ->whereIn('client_name', $names)
            ->where('date', $hoy)
            ->selectRaw('COALESCE(SUM(bytes_in),0) as bytes_in, COALESCE(SUM(bytes_out),0) as bytes_out')
            ->first();

        $inicioMes = now()->startOfMonth()->toDateString();
        $consumoMes = DB::table('daily_internet_consumptions')
            ->whereIn('client_name', $names)
            ->whereBetween('date', [$inicioMes, $hoy])
            ->selectRaw('COALESCE(SUM(bytes_in),0) as bytes_in, COALESCE(SUM(bytes_out),0) as bytes_out')
            ->first();

        $historico = DB::table('daily_internet_consumptions')
            ->whereIn('client_name', $names)
            ->orderByDesc('date')
            ->limit(30)
            ->get(['date', 'bytes_in', 'bytes_out']);

        $hayDatos = $actividad !== null || $historico->isNotEmpty();

        return view('addon-portal-cliente::consumo', compact(
            'cmi', 'plan', 'actividad', 'consumoHoy', 'consumoMes', 'historico', 'hayDatos'
        ));
    }
}
