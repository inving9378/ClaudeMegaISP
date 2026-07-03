<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Payments\Support\ConciliationSettings;
use Illuminate\Http\Request;

/**
 * Panel de interruptores de la automatización de conciliación (WhatsApp IA).
 *
 * SEGURIDAD: gateado por role:super-administrator en la ruta (frontera dura).
 * Tere, Diana y cualquier otro rol reciben 403 y no ven la tarjeta. El estado
 * se persiste en conciliation_settings y la lógica de conciliación lo lee vía
 * ConciliationSettings::enabled() → efecto real inmediato.
 */
class ConciliationConfigController extends Controller
{
    /** Defensa en profundidad: además del middleware, revalidamos el rol. */
    private function guard(): void
    {
        abort_unless(auth()->user()?->hasRole('super-administrator'), 403);
    }

    public function index()
    {
        $this->guard();

        // no-store: la pantalla lleva el estado de los flags embebido (SWITCHES)
        // y JS inline; evitamos que un navegador/proxy sirva una copia vieja.
        return response()
            ->view('addon-payments::conciliacion-config', [
                'switches' => ConciliationSettings::state(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function update(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'key'     => ['required', 'string', 'in:' . implode(',', array_keys(ConciliationSettings::KEYS))],
            'enabled' => ['required', 'boolean'],
        ]);

        ConciliationSettings::set($data['key'], (bool) $data['enabled'], auth()->id());

        return response()->json([
            'ok'       => true,
            'key'      => $data['key'],
            'enabled'  => (bool) $data['enabled'],
            'switches' => ConciliationSettings::state(),
        ]);
    }
}
