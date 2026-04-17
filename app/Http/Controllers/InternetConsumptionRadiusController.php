<?php

namespace App\Http\Controllers;

use App\Models\ClientInternetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternetConsumptionRadiusController extends Controller
{
    public function index(Request $request)
    {
        $mesActual = $request->get('month', Carbon::now()->month);
        $anioActual = $request->get('year', Carbon::now()->year);

        $consumos = DB::connection('radius')->table('radacct')
            ->select(
                'username',
                // Obtenemos la última IP registrada para este usuario
                DB::raw('MAX(framedipaddress) as last_ip'),
                DB::raw('SUM(acctinputoctets) as upload'),
                DB::raw('SUM(acctoutputoctets) as download'),
                DB::raw('SUM(acctsessiontime) as total_time'),
                DB::raw('COUNT(radacctid) as total_sessions')
            )
            ->whereMonth('acctstarttime', $mesActual)
            ->whereYear('acctstarttime', $anioActual)
            ->groupBy('username')
            ->get();

        $nombresClientes = ClientInternetService::pluck('client_name', 'client_name')->toArray();

        $data = $consumos->map(function ($item) use ($nombresClientes) {
            $total_segundos = $item->total_time;

            return [
                'username' => $item->username,
                'real_name' => $nombresClientes[$item->username] ?? 'N/A',
                'ip' => $item->last_ip ?? '0.0.0.0', // <--- LA NUEVA IP
                'upload' => $this->formatBytes($item->upload),
                'download' => $this->formatBytes($item->download),
                'total_gb' => round(($item->upload + $item->download) / 1024 / 1024 / 1024, 2),
                'sessions' => $item->total_sessions,
                'time' => $total_segundos ? sprintf('%02d:%02d:%02d', ($total_segundos / 3600), ($total_segundos / 60 % 60), $total_segundos % 60) : '00:00:00'
            ];
        });

        return view('meganet.admin.consumption.index', compact('data', 'mesActual', 'anioActual'));
    }

    /**
     * Detalle de consumo por sesiones (en construcción)
     */
    public function show($username)
    {
        // Buscamos las sesiones en la base de datos de Radius
        $sesiones = DB::connection('radius')->table('radacct')
            ->where('username', $username)
            ->orderBy('acctstarttime', 'desc')
            ->paginate(20);

        // Opcional: Obtener el nombre real desde Meganet para el título
        $cliente = \App\Models\ClientInternetService::where('client_name', $username)->first();
        $nombreReal = $cliente ? $cliente->client_name : $username;

        return view('meganet.admin.consumption.show', compact('sesiones', 'username', 'nombreReal'));
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
