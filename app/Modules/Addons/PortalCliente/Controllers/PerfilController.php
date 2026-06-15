<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function index()
    {
        $cmi = Auth::guard('cliente')->user();
        return view('addon-portal-cliente::perfil', compact('cmi'));
    }

    /**
     * Cambiar contraseña del portal (bcrypt).
     */
    public function cambiarPassword(Request $request)
    {
        $data = $request->validate([
            'contrasena_actual'    => 'required|string',
            'nueva_contrasena'     => ['required', 'confirmed', Password::min(8)],
        ]);

        $cmi = Auth::guard('cliente')->user();

        if (! $cmi->portal_password || ! Hash::check($data['contrasena_actual'], $cmi->portal_password)) {
            return back()->withErrors(['contrasena_actual' => 'La contraseña actual es incorrecta.']);
        }

        $cmi->update(['portal_password' => Hash::make($data['nueva_contrasena'])]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    /**
     * Actualiza datos de contacto del cliente autenticado.
     *
     * Solo campos de contacto: email, teléfono, dirección.
     * Campos financieros, de plan y de acceso quedan fuera del alcance.
     * Cada cambio se audita en portal_profile_change_log ANTES de aplicarlo.
     */
    public function actualizarDatos(Request $request)
    {
        $data = $request->validate([
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|regex:/^[0-9\-\+\s\(\)]{7,20}$/|max:20',
            'street'          => 'nullable|string|max:255',
            'external_number' => 'nullable|string|max:50',
            'internal_number' => 'nullable|string|max:50',
        ]);

        $cmi      = Auth::guard('cliente')->user();
        $ip       = $request->ip();
        $ua       = substr($request->userAgent() ?? '', 0, 500);
        $camposMod = 0;

        $camposEditables = ['email', 'phone', 'street', 'external_number', 'internal_number'];

        DB::transaction(function () use ($cmi, $data, $camposEditables, $ip, $ua, &$camposMod) {
            foreach ($camposEditables as $campo) {
                if (! array_key_exists($campo, $data)) {
                    continue;
                }

                $nuevo   = $data[$campo] ?? null;
                $anterior = $cmi->$campo ?? null;

                if ($nuevo === $anterior) {
                    continue;
                }

                // Auditar ANTES de aplicar
                DB::table('portal_profile_change_log')->insert([
                    'client_id'     => $cmi->client_id,
                    'campo'         => $campo,
                    'valor_anterior' => $anterior,
                    'valor_nuevo'   => $nuevo,
                    'ip'            => $ip,
                    'user_agent'    => $ua,
                    'created_at'    => now(),
                ]);

                $cmi->$campo = $nuevo;
                $camposMod++;
            }

            if ($camposMod > 0) {
                $cmi->save();
            }
        });

        if ($camposMod === 0) {
            return back()->with('info', 'Sin cambios para guardar.');
        }

        return back()->with('success', 'Datos actualizados correctamente. Los cambios han sido registrados.');
    }
}
