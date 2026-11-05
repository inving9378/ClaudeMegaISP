<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Páginas del portal que montan las MISMAS pantallas Vue del panel de Flotas (#654, F3:
 * mapa global y geocercas). Los datos llegan por /portal/flotas/api (contexto 'flotas.portal').
 */
class FlotasPanelController extends Controller
{
    public function mapa()
    {
        return $this->pagina('Mapa', '<fleet-map base-url="' . e(url('/portal/flotas')) . '"></fleet-map>');
    }

    /** Misma URL que usa la página vieja para su JSON (Accept: json) → se conserva hasta F5. */
    public function geocercas(Request $request)
    {
        if ($request->expectsJson()) {
            return app(FlotasGeocercasController::class)->index();
        }

        return $this->pagina('Geocercas', '<fleet-geofence-list base-url="' . e(url('/portal/flotas')) . '"></fleet-geofence-list>');
    }

    public function geocercaForm(?int $id = null)
    {
        return $this->pagina('Geocerca', '<fleet-geofence-form base-url="' . e(url('/portal/flotas')) . '" :geofence-id="' . (int) $id . '"></fleet-geofence-form>');
    }

    public function geocercaShow(int $id)
    {
        return $this->pagina('Geocerca', '<fleet-geofence-show base-url="' . e(url('/portal/flotas')) . '" :geofence-id="' . $id . '"></fleet-geofence-show>');
    }

    private function pagina(string $titulo, string $componente)
    {
        return view('addon-portal-cliente::flotas_panel', ['titulo' => $titulo, 'componente' => $componente]);
    }
}
