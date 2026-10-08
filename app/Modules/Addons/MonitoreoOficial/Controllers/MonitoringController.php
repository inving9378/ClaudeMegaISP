<?php

namespace App\Modules\Addons\MonitoreoOficial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCase;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringModuleConnection;
use App\Modules\Addons\MonitoreoOficial\Services\CapabilityDetector;

class MonitoringController extends Controller
{
    public function dashboard()
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        // Mantener el panel de conexiones al día (best-effort, idempotente).
        try {
            app(CapabilityDetector::class)->refreshConnections();
        } catch (\Throwable $e) {
            // Nunca romper el dashboard por la detección.
        }

        $connections = MonitoringModuleConnection::where('consumer_module', 'addon-monitoreo-oficial')
            ->orderBy('provider_module')->get();

        $cases = MonitoringCase::orderByDesc('id')->get();

        return view('addon-monitoreo-oficial::dashboard', compact('connections', 'cases'));
    }

    public function configuracion()
    {
        abort_unless(auth()->user()?->can('monitoring.manage_integrations'), 403);

        $connections = MonitoringModuleConnection::where('consumer_module', 'addon-monitoreo-oficial')
            ->orderBy('provider_module')->get();

        return view('addon-monitoreo-oficial::configuracion', compact('connections'));
    }
}
