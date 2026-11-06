<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Controllers\Concerns\PortalAccountRateLimiting;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use PortalAccountRateLimiting;

    // ── LOGIN ─────────────────────────────────────────────────────────────

    public function showLogin(Request $request)
    {
        // ?cliente=N viene del enlace «Portal del cliente» de la ficha del admin: solo prellena el
        // identificador (vía old(), que la vista ya usa). No autentica nada.
        $cliente = (string) $request->query('cliente', '');
        $cliente = preg_match('/^\d{1,10}$/', $cliente) ? (int) $cliente : null;

        if (Auth::guard('cliente')->check()) {
            // Con sesión de OTRO cliente abierta en el navegador, mostrar el login del pedido
            // en vez de mandar al portal del cliente equivocado.
            if ($cliente === null || (int) Auth::guard('cliente')->user()->client_id === $cliente) {
                return redirect()->route('portal.dashboard');
            }
        }

        if ($cliente !== null && !$request->session()->hasOldInput('identificador')) {
            $request->session()->now('_old_input', ['identificador' => (string) $cliente]);
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

        // #256: limitador POR CUENTA además del de IP — frena brute force distribuido (IPs
        // rotativas) contra un mismo identificador. Keyed por el id resuelto de la cuenta
        // (así "004981"/"4981" comparten el mismo contador) o, si no resolvió, por el
        // identificador normalizado (también protege contra enumeración con IPs rotativas).
        $accountKey = $this->accountRateLimitKey('portal-login-cuenta', $cmi, $input);
        if (RateLimiter::tooManyAttempts($accountKey, 10)) {
            $seconds = RateLimiter::availableIn($accountKey);
            return back()->withErrors([
                'identificador' => "Demasiados intentos para esta cuenta. Intenta en {$seconds} segundos.",
            ]);
        }

        // Credencial = "Contraseña WEB" (columna `password`, texto plano).
        // hash_equals evita timing attacks aunque la comparación sea en claro.
        // #1155: login híbrido. La columna legacy manda mientras exista: la ficha del admin y el perfil
        // del portal la cambian sin tocar el hash, y una contraseña anterior no debe seguir entrando.
        // El hash solo decide si no hay legacy; si la legacy coincide, se (re)hashea tras el login.
        $passwordOk = false;
        $needsRehash = false;
        if ($cmi) {
            $contrasena = (string) $data['contrasena'];
            if (filled($cmi->password)) {
                $passwordOk = hash_equals((string) $cmi->password, $contrasena);
                $needsRehash = $passwordOk
                    && ! (filled($cmi->web_password_hash) && Hash::check($contrasena, $cmi->web_password_hash));
            } elseif (filled($cmi->web_password_hash)) {
                $passwordOk = Hash::check($contrasena, $cmi->web_password_hash);
            }
        }

        if (! $passwordOk) {
            RateLimiter::hit($key, 60);
            $this->hitAccountRateLimiter($accountKey, 'login');
            return back()->withErrors([
                'identificador' => 'Credenciales incorrectas. Verifica tu número de cliente o email y contraseña.',
            ])->withInput(['identificador' => $data['identificador']]);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);
        Auth::guard('cliente')->login($cmi);

        $cmi->update(['portal_last_login_at' => now()]);
        if ($needsRehash) {
            // El deploy no entra en mantenimiento: mientras corre la migración la columna aún no existe,
            // y eso no debe tumbar un login que ya fue correcto.
            try {
                $cmi->web_password_hash = Hash::make((string) $data['contrasena']);
                $cmi->save();
            } catch (\Throwable $e) {
                Log::warning('Portal: no se pudo guardar web_password_hash', ['client_id' => $cmi->client_id, 'error' => $e->getMessage()]);
            }
        }

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

        // #256: limitador POR CUENTA (numero_cliente), independiente de la IP.
        $accountKey = $this->accountRateLimitKey('portal-registro-cuenta', $cmi, (string) $data['numero_cliente']);
        if (RateLimiter::tooManyAttempts($accountKey, 10)) {
            $seconds = RateLimiter::availableIn($accountKey);
            return back()->withErrors(['numero_cliente' => "Demasiados intentos para esta cuenta. Intenta en {$seconds} segundos."]);
        }

        // Verificación: número de cliente + teléfono coinciden
        $telefonoOk = $this->telefonoCoincide($cmi, $data['telefono']);

        if (! $telefonoOk) {
            RateLimiter::hit($key, 300);
            $this->hitAccountRateLimiter($accountKey, 'registro');
            // Mensaje genérico — anti-enumeración
            return back()->withErrors([
                'numero_cliente' => 'No encontramos un cliente con ese número y teléfono. Verifica tus datos o contacta a soporte.',
            ]);
        }

        // #473: con el flujo por código encendido, registro no puede ser la vía para cambiar la contraseña
        // de una cuenta ya registrada sin pasar por el código.
        if (config('portalcliente.recuperar_otp') && filled($cmi->portal_registered_at)) {
            return redirect()->route('portal.recuperar')
                ->with('info', 'Este número de cliente ya tiene cuenta. Recupera tu contraseña con un código por WhatsApp.');
        }

        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);
        // Reapuntado a la columna `password` (Contraseña WEB, texto plano) — unificado con el login.
        // Asignación directa para no ampliar el $fillable del modelo.
        $cmi->password             = $data['contrasena'];
        $cmi->web_password_hash    = Hash::make($data['contrasena']);
        $cmi->portal_registered_at = now();
        $cmi->portal_last_login_at = now();
        $cmi->save();

        Auth::guard('cliente')->login($cmi);

        return redirect()->route('portal.dashboard')->with('success', '¡Bienvenido al portal! Tu cuenta ha sido creada.');
    }

    // ── RECUPERAR CONTRASEÑA ──────────────────────────────────────────────

    /**
     * Un teléfono sin dígitos se normaliza a '' y coincidiría con cualquier phone2/phone3 vacío, así que
     * nunca cuenta como prueba de identidad.
     */
    private function telefonoCoincide(?PortalClient $cmi, string $capturado): bool
    {
        $capturado = $this->normalizeTel($capturado);
        if (! $cmi || $capturado === '') {
            return false;
        }

        foreach ([$cmi->phone, $cmi->phone2, $cmi->phone3] as $registrado) {
            if ($this->normalizeTel($registrado) === $capturado) {
                return true;
            }
        }

        return false;
    }

    public function showRecuperar()
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.dashboard');
        }
        return view('addon-portal-cliente::auth.recuperar');
    }

    public function recuperar(Request $request)
    {
        // #473: con el flujo por código encendido este POST ya no cambia contraseñas. Corta antes de
        // validar o buscar la cuenta para no responder distinto según los datos (formularios abiertos de antes).
        if (config('portalcliente.recuperar_otp')) {
            return redirect()->route('portal.recuperar')
                ->with('info', 'Para proteger tu cuenta, ahora te enviamos un código por WhatsApp. Solicítalo aquí.');
        }

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

        // #256: limitador POR CUENTA (numero_cliente), independiente de la IP.
        $accountKey = $this->accountRateLimitKey('portal-recuperar-cuenta', $cmi, (string) $data['numero_cliente']);
        if (RateLimiter::tooManyAttempts($accountKey, 10)) {
            $seconds = RateLimiter::availableIn($accountKey);
            return back()->withErrors(['numero_cliente' => "Demasiados intentos para esta cuenta. Intenta en {$seconds} segundos."]);
        }

        $telefonoOk = $this->telefonoCoincide($cmi, $data['telefono']);

        if (! $telefonoOk) {
            RateLimiter::hit($key, 300);
            $this->hitAccountRateLimiter($accountKey, 'recuperar');
            return back()->withErrors([
                'numero_cliente' => 'No encontramos un cliente con ese número y teléfono.',
            ]);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);
        // Reapuntado a la columna `password` (Contraseña WEB, texto plano) — unificado con el login.
        $cmi->password = $data['contrasena'];
        $cmi->web_password_hash = Hash::make($data['contrasena']);
        $cmi->save();

        return redirect()->route('portal.login')
            ->with('success', 'Contraseña actualizada. Ahora puedes iniciar sesión.');
    }
}
