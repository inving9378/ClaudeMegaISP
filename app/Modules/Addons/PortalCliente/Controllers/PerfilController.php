<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function index()
    {
        $cmi = Auth::guard('cliente')->user();
        return view('addon-portal-cliente::perfil', compact('cmi'));
    }

    /**
     * Cambiar contraseña del portal.
     *
     * Alineado a Fase 2: valida y escribe en la columna `password` (texto plano,
     * la "Contraseña WEB" de la ficha). Cambiarla aquí se refleja en la ficha del
     * admin — consistente con la decisión de autoservicio.
     */
    public function cambiarPassword(Request $request)
    {
        $data = $request->validate([
            'contrasena_actual'    => 'required|string',
            'nueva_contrasena'     => ['required', 'confirmed', Password::min(8)],
        ]);

        $cmi = Auth::guard('cliente')->user();

        if (! filled($cmi->password) || ! hash_equals((string) $cmi->password, (string) $data['contrasena_actual'])) {
            return back()->withErrors(['contrasena_actual' => 'La contraseña actual es incorrecta.']);
        }

        $cmi->password = $data['nueva_contrasena'];
        $cmi->save();

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
