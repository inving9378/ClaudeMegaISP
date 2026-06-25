<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // ── LOGIN ─────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.dashboard');
        }
        return view('addon-portal-cliente::auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'identificador' => 'required|string',
            'contrasena'    => 'required|string',
        ]);

        $key = 'portal-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'identificador' => "Demasiados intentos. Intenta en {$seconds} segundos.",
            ]);
        }

        $input = trim($data['identificador']);

        // Identificador: email, o Usuario WEB (columna `user`) / número de cliente (`client_id`).
        // Tolera el padding inconsistente de la ficha ("004981" vs "4981").
        $cmi = null;
        if (str_contains($input, '@')) {
            $cmi = PortalClient::where('email', $input)->first();
        } else {
            $norm = ltrim($input, '0');                 // "004981" -> "4981"
            $cmi = PortalClient::where('user', $input)
                ->orWhere('user', $norm)
                ->orWhere('client_id', (int) $norm)
                ->first();
        }

        // Credencial = "Contraseña WEB" (columna `password`, texto plano).
        // hash_equals evita timing attacks aunque la comparación sea en claro.
        $passwordOk = $cmi
            && filled($cmi->password)
            && hash_equals((string) $cmi->password, (string) $data['contrasena']);

        if (! $passwordOk) {
            RateLimiter::hit($key, 60);
            return back()->withErrors([
                'identificador' => 'Credenciales incorrectas. Verifica tu número de cliente o email y contraseña.',
            ])->withInput(['identificador' => $data['identificador']]);
        }

        RateLimiter::clear($key);
        Auth::guard('cliente')->login($cmi);

        $cmi->update(['portal_last_login_at' => now()]);

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('cliente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('portal.login')->with('success', 'Sesión cerrada correctamente.');
    }

    // ── AUTO-REGISTRO ─────────────────────────────────────────────────────

    public function showRegistro()
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.dashboard');
        }
        return view('addon-portal-cliente::auth.registro');
    }

    public function registro(Request $request)
    {
        $data = $request->validate([
            'numero_cliente' => 'required|integer|min:1',
            'telefono'       => 'required|string|max:30',
            'contrasena'     => ['required', 'confirmed', Password::min(8)],
        ]);

        $key = 'portal-registro:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['numero_cliente' => "Demasiados intentos. Intenta en {$seconds} segundos."]);
        }

        $cmi = PortalClient::where('client_id', (int) $data['numero_cliente'])->first();

        // Verificación: número de cliente + teléfono coinciden
        $telefonoOk = $cmi && (
            $this->normalizeTel($cmi->phone)  === $this->normalizeTel($data['telefono']) ||
            $this->normalizeTel($cmi->phone2) === $this->normalizeTel($data['telefono']) ||
            $this->normalizeTel($cmi->phone3) === $this->normalizeTel($data['telefono'])
        );

        if (! $telefonoOk) {
            RateLimiter::hit($key, 300);
            // Mensaje genérico — anti-enumeración
            return back()->withErrors([
                'numero_cliente' => 'No encontramos un cliente con ese número y teléfono. Verifica tus datos o contacta a soporte.',
            ]);
        }

        RateLimiter::clear($key);
        // Reapuntado a la columna `password` (Contraseña WEB, texto plano) — unificado con el login.
        // Asignación directa para no ampliar el $fillable del modelo.
        $cmi->password             = $data['contrasena'];
        $cmi->portal_registered_at = now();
        $cmi->portal_last_login_at = now();
        $cmi->save();

        Auth::guard('cliente')->login($cmi);

        return redirect()->route('portal.dashboard')->with('success', '¡Bienvenido al portal! Tu cuenta ha sido creada.');
    }

    // ── RECUPERAR CONTRASEÑA ──────────────────────────────────────────────

    public function showRecuperar()
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.dashboard');
        }
        return view('addon-portal-cliente::auth.recuperar');
    }

    public function recuperar(Request $request)
    {
        $data = $request->validate([
            'numero_cliente' => 'required|integer|min:1',
            'telefono'       => 'required|string|max:30',
            'contrasena'     => ['required', 'confirmed', Password::min(8)],
        ]);

        $key = 'portal-recuperar:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['numero_cliente' => "Demasiados intentos. Intenta en {$seconds} segundos."]);
        }

        $cmi = PortalClient::where('client_id', (int) $data['numero_cliente'])->first();

        $telefonoOk = $cmi && (
            $this->normalizeTel($cmi->phone)  === $this->normalizeTel($data['telefono']) ||
            $this->normalizeTel($cmi->phone2) === $this->normalizeTel($data['telefono']) ||
            $this->normalizeTel($cmi->phone3) === $this->normalizeTel($data['telefono'])
        );

        if (! $telefonoOk) {
            RateLimiter::hit($key, 300);
            return back()->withErrors([
                'numero_cliente' => 'No encontramos un cliente con ese número y teléfono.',
            ]);
        }

        RateLimiter::clear($key);
        // Reapuntado a la columna `password` (Contraseña WEB, texto plano) — unificado con el login.
        $cmi->password = $data['contrasena'];
        $cmi->save();

        return redirect()->route('portal.login')
            ->with('success', 'Contraseña actualizada. Ahora puedes iniciar sesión.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function normalizeTel(?string $tel): string
    {
        if (! $tel) return '';
        return preg_replace('/[^0-9]/', '', $tel);
    }
}
