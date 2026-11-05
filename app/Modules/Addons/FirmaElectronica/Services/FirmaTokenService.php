<?php

namespace App\Modules\Addons\FirmaElectronica\Services;

use App\Modules\Addons\FirmaElectronica\Models\FirmaEvidencia;
use App\Modules\Addons\FirmaElectronica\Models\FirmaParticipante;
use App\Modules\Addons\FirmaElectronica\Models\FirmaSolicitud;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Enlace del firmante: token aleatorio guardado hasheado (sha256), caducidad, un solo uso,
 * OTP con tope de intentos. Falla cerrado (patrón PortalPagoLinkService): todo rechazo es null/false.
 */
class FirmaTokenService
{
    public function emitir(FirmaParticipante $p, ?Carbon $expira = null): string
    {
        $token = Str::random(48);
        $p->update([
            'token_hash' => hash('sha256', $token),
            'expira_at' => $expira ?? now()->addHours((int) config('firma_electronica.token_ttl_horas', 72)),
            'usado_at' => null, 'otp_hash' => null, 'intentos' => 0,
        ]);
        return $token;
    }

    /** Resuelve un token vigente: existe, no vencido, no usado, solicitud no terminal y turno válido. */
    public function resolver(string $token): ?FirmaParticipante
    {
        if (strlen($token) !== 48) {
            return null;
        }
        $p = FirmaParticipante::where('token_hash', hash('sha256', $token))->first();
        if (! $p || $p->usado_at || ! $p->expira_at || $p->expira_at->isPast() || $p->estado === 'firmado') {
            return null;
        }
        $s = $p->solicitud;
        if (! $s || $s->esTerminal() || $s->id_externo === null) {
            return null;
        }
        if ($s->orden_secuencial && $s->participantes()->where('orden', '<', $p->orden)->where('estado', '!=', 'firmado')->exists()) {
            return null;
        }
        return $p;
    }

    /** Genera el OTP (en claro para enviarlo por correo; en BD solo el hash). */
    public function generarOtp(FirmaParticipante $p): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $p->update([
            'otp_hash' => hash('sha256', $otp . $p->id),
            'otp_expira_at' => now()->addMinutes((int) config('firma_electronica.otp_ttl_minutos', 10)),
            'intentos' => 0,
        ]);
        return $otp;
    }

    public function verificarOtp(FirmaParticipante $p, string $otp): bool
    {
        $p->refresh();
        if (! $p->otp_hash || ! $p->otp_expira_at || $p->otp_expira_at->isPast()
            || $p->intentos >= (int) config('firma_electronica.otp_max_intentos', 5)) {
            return false;
        }
        $p->increment('intentos');
        return hash_equals($p->otp_hash, hash('sha256', $otp . $p->id));
    }

    /**
     * Firma del participante a partir de su enlace. Exige enlace vigente, OTP verificado, identidad,
     * consentimiento expreso y huella del original. Consume el enlace (un solo uso) y deja evidencia;
     * la solicitud avanza por evento autenticado, nunca por redirección del navegador.
     */
    public function firmar(string $token, string $otp, string $identidadDeclarada, bool $consentimiento, FirmaSolicitudService $svc): ?FirmaEvidencia
    {
        if (! $consentimiento || trim($identidadDeclarada) === '') {
            return null;
        }
        $p = $this->resolver($token);
        if (! $p) {
            return null;
        }
        $s = $p->solicitud;
        if (! $s->sha256_original || ! $this->verificarOtp($p, $otp)) {
            return null;
        }
        $p->refresh();
        $p->update(['usado_at' => now(), 'otp_hash' => null]);

        $ev = FirmaEvidencia::create([
            'solicitud_id' => $s->id, 'participante_id' => $p->id,
            'identidad_declarada' => $identidadDeclarada, 'correo' => $p->correo,
            'metodo_autenticacion' => 'otp_correo', 'consentimiento' => true,
            'zona_horaria' => config('app.timezone'), 'id_transaccion' => (string) Str::uuid(),
            'sha256_original' => $s->sha256_original, 'firmado_at' => now(),
        ]);
        $svc->aplicarEvento($s, 'int-' . $ev->id_transaccion, 'participante_firmado', ['participante_id' => $p->id]);
        return $ev;
    }
}
