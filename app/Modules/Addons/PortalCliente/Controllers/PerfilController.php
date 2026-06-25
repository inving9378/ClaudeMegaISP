<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}
