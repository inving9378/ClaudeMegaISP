<?php

namespace App\Modules\Addons\FirmaElectronica\Controllers;

use App\Modules\Addons\CrmEmpresarial\Models\DocumentoEmision;
use App\Modules\Addons\FirmaElectronica\Mail\FirmaCorreo;
use App\Modules\Addons\FirmaElectronica\Services\FirmaSolicitudService;
use App\Modules\Addons\FirmaElectronica\Services\FirmaTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Pantalla pública del firmante. Todo rechazo del token es el mismo 404 (falla cerrado, sin pistas). */
class FirmaPublicaController
{
    public function __construct(private FirmaTokenService $tokens) {}

    public function mostrar(string $token)
    {
        $p = $this->tokens->resolver($token) ?? abort(404);
        $s = $p->solicitud;
        $emision = $s->emision_id ? DocumentoEmision::find($s->emision_id) : null;

        return view('addon-firma-electronica::firma', [
            'token' => $token, 'participante' => $p, 'solicitud' => $s,
            'folio' => $emision?->folio, 'tieneDocumento' => (bool) ($emision?->pdf_path),
            'correoActivo' => (bool) config('firma_electronica.correo_activo'),
        ]);
    }

    public function documento(string $token)
    {
        $p = $this->tokens->resolver($token) ?? abort(404);
        $emision = $p->solicitud->emision_id ? DocumentoEmision::find($p->solicitud->emision_id) : null;
        abort_unless($emision?->pdf_path && Storage::disk('local')->exists($emision->pdf_path), 404);

        return response()->file(Storage::disk('local')->path($emision->pdf_path), ['Content-Type' => 'application/pdf']);
    }

    public function enviarOtp(string $token)
    {
        $p = $this->tokens->resolver($token) ?? abort(404);
        if (! config('firma_electronica.correo_activo')) {
            return response()->json(['ok' => false, 'mensaje' => 'El envío de códigos por correo aún no está activado.'], 503);
        }
        $otp = $this->tokens->generarOtp($p);
        Mail::to($p->correo)->send(new FirmaCorreo(
            'Tu código para firmar',
            '<p>Hola ' . e($p->nombre) . ',</p><p>Tu código de verificación es <strong>' . $otp . '</strong>. Vence en '
            . (int) config('firma_electronica.otp_ttl_minutos', 10) . ' minutos.</p>'
        ));

        return response()->json(['ok' => true, 'mensaje' => 'Te enviamos un código a tu correo.']);
    }

    public function firmar(Request $request, string $token, FirmaSolicitudService $svc)
    {
        $d = $request->validate([
            'otp' => 'required|digits:6', 'identidad' => 'required|string|max:255',
            'consentimiento' => 'accepted', 'firma' => 'required|string|max:400000',
        ]);
        $p = $this->tokens->resolver($token) ?? abort(404);
        $png = preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $d['firma'], $m) ? base64_decode($m[1], true) : false;
        if ($png === false || strncmp($png, "\x89PNG", 4) !== 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Dibuja tu firma para continuar.'], 422);
        }

        $ev = $this->tokens->firmar($token, $d['otp'], $d['identidad'], true, $svc);
        if (! $ev) {
            return response()->json(['ok' => false, 'mensaje' => 'El código es incorrecto o venció.'], 422);
        }
        Storage::disk('local')->put("private/firma/{$p->solicitud_id}/{$p->id}.png", $png);

        return response()->json(['ok' => true, 'mensaje' => 'Documento firmado. Gracias.']);
    }
}
