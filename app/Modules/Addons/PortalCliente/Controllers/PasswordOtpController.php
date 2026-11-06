<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Modules\Addons\PortalCliente\Controllers\Concerns\PortalAccountRateLimiting;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use App\Modules\Addons\PortalCliente\Models\PortalPasswordOtp;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Item #473 — recuperar la contraseña del Portal Cliente con un código por WhatsApp.
 * Detrás de portalcliente.recuperar_otp: apagado, estas rutas responden 404 y sigue
 * funcionando AuthController::recuperar.
 *
 *   1. solicitarOtp: número de cliente + teléfono. La respuesta es la misma exista o no la cuenta.
 *   2. verificarOtp: máximo 5 intentos por código, vigencia de 10 min. Si es correcto emite un
 *      token de un solo uso (vigencia de 5 min) para el paso 3.
 *   3. restablecer: con ese token fija la contraseña nueva y lo invalida.
 *
 * El id de la fila y el token viajan solo en la sesión del servidor, nunca en el HTML ni en la URL.
 */
class PasswordOtpController extends Controller
{
    use PortalAccountRateLimiting;

    private const OTP_TTL_MINUTES         = 10;
    private const RESET_TOKEN_TTL_MINUTES = 5;
    private const SESSION_KEY             = 'portal_password_otp';

    private const SOLICITUDES_POR_CUENTA = 3;
    // Más holgado que por cuenta: varios clientes pueden salir a internet por la misma IP.
    private const SOLICITUDES_POR_IP     = 10;
    private const VENTANA_SOLICITUDES    = 3600;

    private const VERIFICACIONES_POR_IP = 10;
    private const VENTANA_VERIFICACION  = 600;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(config('portalcliente.recuperar_otp'), 404);

            return $next($request);
        });
    }

    // ── PASO 1: solicitar el código ─────────────────────────────────────────

    public function solicitarOtp(Request $request)
    {
        $data = $request->validate([
            'numero_cliente' => 'required|integer|min:1',
            'telefono'       => 'required|string|max:30',
        ]);

        $numeroCliente = (int) $data['numero_cliente'];
        $cmi = PortalClient::where('client_id', $numeroCliente)->first();

        $ipKey = 'portal-otp-solicitar:' . $request->ip();
        // Se cuenta TODA solicitud, coincida o no el teléfono: si solo contaran las correctas,
        // el aviso de «demasiados intentos» confirmaría que un teléfono es el de la cuenta.
        $accountKey = $this->accountRateLimitKey('portal-otp-solicitar-cuenta', $cmi, (string) $numeroCliente);

        if (RateLimiter::tooManyAttempts($ipKey, self::SOLICITUDES_POR_IP)
            || RateLimiter::tooManyAttempts($accountKey, self::SOLICITUDES_POR_CUENTA)) {
            $seconds = max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($accountKey));

            return redirect()->route('portal.recuperar')
                ->withErrors(['numero_cliente' => "Demasiados intentos. Intenta en {$seconds} segundos."]);
        }

        RateLimiter::hit($ipKey, self::VENTANA_SOLICITUDES);
        RateLimiter::hit($accountKey, self::VENTANA_SOLICITUDES);

        // El hash se calcula siempre: bcrypt tarda lo bastante para que, de hacerlo solo con
        // cuenta y teléfono correctos, el tiempo de respuesta delatara cuáles lo son.
        $codigo = (string) random_int(100000, 999999);
        $hash = Hash::make($codigo);

        $telefono = $this->telefonoCoincidente($cmi, $data['telefono']);
        $otpId = $telefono !== null ? $this->crearYEnviarOtp($cmi, $telefono, $codigo, $hash, $request) : null;

        if ($otpId === null) {
            Log::warning('portal-password-otp: solicitud sin código enviado', ['account_key' => $accountKey]);
        }

        // Sin fila (otp_id null) el paso 2 rechaza cualquier código con el mismo mensaje.
        $request->session()->put(self::SESSION_KEY, ['otp_id' => $otpId, 'verified' => false]);

        return redirect()->route('portal.recuperar.verificar')
            ->with('success', 'Si los datos son correctos, te enviamos un código por WhatsApp al teléfono registrado.');
    }

    /** El teléfono de la ficha que coincide con el capturado. Uno sin dígitos no coincide con los campos vacíos. */
    private function telefonoCoincidente(?PortalClient $cmi, string $capturado): ?string
    {
        $capturado = $this->normalizeTel($capturado);
        if (! $cmi || $capturado === '') {
            return null;
        }

        foreach ([$cmi->phone, $cmi->phone2, $cmi->phone3] as $registrado) {
            if ($this->normalizeTel($registrado) === $capturado) {
                return $registrado;
            }
        }

        return null;
    }

    private function crearYEnviarOtp(PortalClient $cmi, string $telefono, string $codigo, string $hash, Request $request): ?int
    {
        $numero = $this->normalizeWhatsAppNumber($telefono);
        if (strlen($numero) < 10) {
            return null;
        }

        // Un fallo aquí tampoco debe distinguirse: sería un error 500 solo para cuentas reales.
        try {
            $otp = PortalPasswordOtp::create([
                'client_id'           => $cmi->client_id,
                'otp_hash'            => $hash,
                'canal'               => 'whatsapp',
                'destino_enmascarado' => $this->enmascararTelefono($numero),
                'expires_at'          => now()->addMinutes(self::OTP_TTL_MINUTES),
                'ip'                  => $request->ip(),
                'user_agent'          => substr((string) $request->userAgent(), 0, 500),
            ]);
            $this->auditar($cmi->client_id, 'password_otp_solicitado', $request);
        } catch (\Throwable $e) {
            Log::error('portal-password-otp: no se pudo registrar el código', [
                'client_id' => $cmi->client_id,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }

        // Ya con la respuesta entregada: si el envío va en línea, su demora delataría la cuenta.
        $clientId = $cmi->client_id;
        $mensaje = "Meganet: tu código para recuperar tu contraseña del Portal Cliente es {$codigo}. "
            . 'Vence en ' . self::OTP_TTL_MINUTES . ' minutos. No lo compartas con nadie. Si no lo solicitaste, ignora este mensaje.';

        app()->terminating(function () use ($numero, $mensaje, $clientId) {
            try {
                app(WhatsAppGateway::class)->sendText(Servicios::slugWhatsApp('portalcliente.recuperar_otp'), $numero, $mensaje, [
                    'source' => 'portal_recuperar_otp',
                ]);
            } catch (\Throwable $e) {
                Log::warning('portal-password-otp: fallo al enviar el código por WhatsApp', [
                    'client_id' => $clientId,
                    'error'     => $e->getMessage(),
                ]);
            }
        });

        return $otp->id;
    }

    // ── PASO 2: verificar el código ─────────────────────────────────────────

    public function showVerificar(Request $request)
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('portal.recuperar');
        }

        return view('addon-portal-cliente::auth.recuperar-otp');
    }

    public function verificarOtp(Request $request)
    {
        $data = $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $estado = $request->session()->get(self::SESSION_KEY);
        if (! $estado) {
            return redirect()->route('portal.recuperar');
        }

        $key = 'portal-otp-verificar:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, self::VERIFICACIONES_POR_IP)) {
            $seconds = RateLimiter::availableIn($key);

            return redirect()->route('portal.recuperar.verificar')
                ->withErrors(['otp' => "Demasiados intentos. Intenta en {$seconds} segundos."]);
        }
        RateLimiter::hit($key, self::VENTANA_VERIFICACION);

        $otp = $estado['otp_id'] ? PortalPasswordOtp::find($estado['otp_id']) : null;

        if (! $otp || ! $otp->reservarIntento()) {
            // Mismo costo que Hash::check: el tiempo no debe delatar si detrás hay un código real.
            Hash::make($data['otp']);

            return $this->codigoInvalido();
        }

        if (! Hash::check($data['otp'], $otp->otp_hash)) {
            return $this->codigoInvalido();
        }

        $token = Str::random(48);
        $otp->forceFill([
            'verified_at'            => now(),
            'reset_token_hash'       => Hash::make($token),
            'reset_token_expires_at' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        ])->save();

        $this->auditar($otp->client_id, 'password_otp_verificado', $request);

        // Desde aquí la sesión ya permite cambiar la contraseña: un id de sesión plantado antes deja de servir.
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'otp_id'      => $otp->id,
            'verified'    => true,
            'reset_token' => $token,
        ]);

        return redirect()->route('portal.recuperar.nueva');
    }

    private function codigoInvalido()
    {
        return redirect()->route('portal.recuperar.verificar')
            ->withErrors(['otp' => 'Código incorrecto o expirado. Solicita uno nuevo si es necesario.']);
    }

    // ── PASO 3: fijar la contraseña nueva ───────────────────────────────────

    public function showNueva(Request $request)
    {
        $estado = $request->session()->get(self::SESSION_KEY);
        if (! $estado || empty($estado['verified'])) {
            return redirect()->route('portal.recuperar');
        }

        return view('addon-portal-cliente::auth.recuperar-nueva');
    }

    public function restablecer(Request $request)
    {
        $estado = $request->session()->get(self::SESSION_KEY);
        if (! $estado || empty($estado['verified']) || empty($estado['reset_token']) || empty($estado['otp_id'])) {
            return $this->procesoInvalido($request);
        }

        $otp = PortalPasswordOtp::find($estado['otp_id']);
        if (! $otp || ! $otp->resetTokenValido($estado['reset_token'])) {
            return $this->procesoInvalido($request);
        }

        $data = $request->validate([
            'contrasena' => ['required', 'confirmed', Password::min(8)],
        ]);

        $cmi = PortalClient::where('client_id', $otp->client_id)->first();
        if (! $cmi) {
            return $this->procesoInvalido($request);
        }

        $hash = Hash::make($data['contrasena']);

        $aplicado = DB::transaction(function () use ($cmi, $otp, $data, $hash, $request) {
            // UPDATE condicionado: de dos envíos simultáneos con el mismo token, solo uno pasa.
            $reclamado = PortalPasswordOtp::whereKey($otp->id)
                ->whereNull('reset_token_used_at')
                ->update(['reset_token_used_at' => now()]);
            if ($reclamado !== 1) {
                return false;
            }

            // Las dos columnas, igual que AuthController::recuperar: el login compara primero la legacy (#1155).
            $cmi->password = $data['contrasena'];
            $cmi->web_password_hash = $hash;
            $cmi->save();

            $this->auditar($cmi->client_id, 'password_reset_via_otp', $request);

            return true;
        });

        if (! $aplicado) {
            return $this->procesoInvalido($request);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('portal.login')
            ->with('success', 'Contraseña actualizada. Ahora puedes iniciar sesión.');
    }

    private function procesoInvalido(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('portal.recuperar')
            ->withErrors(['numero_cliente' => 'El proceso expiró o no es válido. Inicia de nuevo.']);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    /** Solo el evento y sus metadatos: ni el código ni la contraseña llegan al log de cambios. */
    private function auditar(int $clientId, string $campo, Request $request): void
    {
        DB::table('portal_profile_change_log')->insert([
            'client_id'      => $clientId,
            'campo'          => $campo,
            'valor_anterior' => null,
            'valor_nuevo'    => null,
            'ip'             => $request->ip(),
            'user_agent'     => substr((string) $request->userAgent(), 0, 500),
            'created_at'     => now(),
        ]);
    }

    /** Igual al formato que usan las conversaciones de WhatsAppAgent (contact_number: MX 10 dígitos → prefijo 521). */
    private function normalizeWhatsAppNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            $digits = '521' . $digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = '521' . substr($digits, 2);
        }

        return $digits;
    }

    private function enmascararTelefono(string $numero): string
    {
        return str_repeat('*', max(strlen($numero) - 4, 0)) . substr($numero, -4);
    }
}
