<?php

namespace App\Modules\Addons\Domiciliacion\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\StandardMail;
use App\Modules\Addons\Domiciliacion\Models\EnrollmentLink;
use App\Modules\Addons\Domiciliacion\Services\DomiciliacionEnrollmentService;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnrollmentLinkController extends Controller
{
    public function __construct(private DomiciliacionEnrollmentService $enrollment) {}

    /**
     * Admin: genera y envía una liga de enrolamiento para un cliente.
     * La liga expira en 48 h y es de un solo uso.
     */
    public function generate(Request $request, int $clientId): JsonResponse
    {
        $data = $request->validate(['channel' => 'required|in:whatsapp,email']);

        $cmi = DB::table('client_main_information')->where('client_id', $clientId)->first();
        if (! $cmi) {
            return response()->json(['success' => false, 'error' => 'Cliente no encontrado.'], 404);
        }

        $link = EnrollmentLink::create([
            'client_id'  => $clientId,
            'token'      => EnrollmentLink::generateToken(),
            'channel'    => $data['channel'],
            'expires_at' => now()->addHours(48),
            'created_by' => Auth::id() ?? 0,
        ]);

        $url = route('domiciliacion.public.show', ['token' => $link->token]);
        $this->enviarLiga($data['channel'], $cmi, $url);

        return response()->json([
            'success' => true,
            'url'     => $url,
            'expires' => $link->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Público: muestra el formulario de tokenización con openpay.js.
     */
    public function show(string $token)
    {
        $link = EnrollmentLink::where('token', $token)->first();

        if (! $link || $link->isUsed() || $link->isExpired()) {
            return view('addon-domiciliacion::enrollment_invalid');
        }

        $cmi = DB::table('client_main_information')->where('client_id', $link->client_id)->first();

        return view('addon-domiciliacion::enrollment_link', [
            'link'       => $link,
            'cmi'        => $cmi,
            'openpayId'  => config('openpay.id'),
            'openpayKey' => config('openpay.public_key'),
            'sandbox'    => config('openpay.sandbox', true),
            'success'    => false,
            'error'      => null,
        ]);
    }

    /**
     * Público: recibe el token generado por openpay.js y guarda la tarjeta.
     */
    public function store(Request $request, string $token)
    {
        $link = EnrollmentLink::where('token', $token)->first();

        if (! $link || $link->isUsed() || $link->isExpired()) {
            return view('addon-domiciliacion::enrollment_invalid');
        }

        $data = $request->validate(['token' => 'required|string|max:100']);

        $cmi = DB::table('client_main_information')->where('client_id', $link->client_id)->first();

        try {
            $this->enrollment->enrolar(
                clientId:     $link->client_id,
                token:        $data['token'],
                channel:      'liga',
                createdBy:    0,
                customerData: [
                    'name'         => $cmi->name ?? 'Cliente',
                    'last_name'    => $cmi->father_last_name ?? '',
                    'email'        => $cmi->email ?? '',
                    'phone_number' => $cmi->phone ?? '',
                ]
            );

            $link->markUsed();

            return view('addon-domiciliacion::enrollment_link', [
                'link'       => $link,
                'cmi'        => $cmi,
                'openpayId'  => config('openpay.id'),
                'openpayKey' => config('openpay.public_key'),
                'sandbox'    => config('openpay.sandbox', true),
                'success'    => true,
                'error'      => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('domiciliacion.liga.store: error al enrolar', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);

            return view('addon-domiciliacion::enrollment_link', [
                'link'       => $link,
                'cmi'        => $cmi,
                'openpayId'  => config('openpay.id'),
                'openpayKey' => config('openpay.public_key'),
                'sandbox'    => config('openpay.sandbox', true),
                'success'    => false,
                'error'      => 'No pudimos guardar tu tarjeta. Verifica los datos e intenta de nuevo.',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function enviarLiga(string $channel, object $cmi, string $url): void
    {
        $nombre  = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));
        $mensaje = "Hola {$nombre}, registra tu tarjeta para el pago automático mensual con Meganet:\n{$url}\n(Válido 48 h, un solo uso)";

        if ($channel === 'whatsapp') {
            try {
                $jid = EvolutionApiService::phoneToJid($cmi->phone ?? '');
                app(EvolutionApiService::class)->sendText($jid, $mensaje);
            } catch (\Throwable $e) {
                Log::warning('domiciliacion.liga: fallo WhatsApp', ['error' => $e->getMessage()]);
            }
            return;
        }

        if ($channel === 'email') {
            $email = $cmi->email ?? null;
            if (! $email) {
                Log::warning('domiciliacion.liga: cliente sin email', ['client_id' => $cmi->client_id]);
                return;
            }
            try {
                Mail::send(new StandardMail([
                    'to'    => $email,
                    'title' => 'Activa tu pago automático mensual — Meganet',
                    'body'  => "<p>Hola {$nombre},</p>"
                        . "<p>Haz clic en el siguiente enlace para registrar tu tarjeta y activar "
                        . "el cobro automático mensual con Meganet:</p>"
                        . "<p><a href=\"{$url}\" style=\"color:#0057A8;font-weight:bold;\">Registrar mi tarjeta</a></p>"
                        . "<p style=\"color:#888;font-size:12px;\">El enlace expira en 48 horas y es de un solo uso.</p>",
                ]));
            } catch (\Throwable $e) {
                Log::warning('domiciliacion.liga: fallo email', ['error' => $e->getMessage()]);
            }
        }
    }
}
