<?php

namespace App\Listeners\Correo;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Interceptación de correo para entornos de prueba (config `mail.interceptar`).
 *
 * Escucha MessageSending, que Laravel dispara en Mailer::send() ANTES de armar el
 * sobre SMTP (Envelope::create corre después, con los destinatarios ya cambiados).
 * Por ahí pasan Mail::send/to/raw, las notificaciones por correo, los mailers de
 * cuentas del Hub (Correo::mailer) y lo encolado al ejecutarse en el worker.
 *
 * Con la interceptación activa:
 *  - To/Cc/Bcc/Reply-To originales se quitan y el único destinatario es `destino`;
 *  - el asunto lleva `prefijo` y se le enmascaran los correos que traiga;
 *  - el cuerpo lleva un aviso visible de prueba;
 *  - si `destino` falta o no es válido, el correo se cancela (return false).
 *
 * Apagada (default) no toca nada: devuelve null y el envío sigue igual.
 */
class InterceptarCorreoDev
{
    public const CABECERA = 'X-MegaISP-Interceptado';

    public const AVISO = 'PRUEBA DE DESARROLLO — correo interceptado. No va dirigido a ningún cliente.';

    public function handle(MessageSending $event)
    {
        $config = config('mail.interceptar', []);
        if (empty($config['activo'])) {
            return null;
        }

        $mensaje = $event->message;
        $destino = trim((string) ($config['destino'] ?? ''));

        if (! $mensaje instanceof Email || filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('correo-interceptado: cancelado (interceptación activa sin destino válido)', [
                'asunto' => self::enmascarar((string) ($mensaje instanceof Email ? $mensaje->getSubject() : '')),
            ]);

            return false;
        }

        $originales = count($mensaje->getTo()) + count($mensaje->getCc()) + count($mensaje->getBcc());

        $cabeceras = $mensaje->getHeaders();
        foreach (['To', 'Cc', 'Bcc', 'Reply-To'] as $nombre) {
            $cabeceras->remove($nombre);
        }
        $mensaje->to(new Address($destino));

        $prefijo = (string) ($config['prefijo'] ?? '[DEV — PRUEBA]');
        $asunto = self::enmascarar((string) $mensaje->getSubject());
        if (! str_starts_with($asunto, $prefijo)) {
            $asunto = trim($prefijo . ' ' . $asunto);
        }
        $mensaje->subject($asunto);

        self::agregarAviso($mensaje, $originales);

        $cabeceras->remove(self::CABECERA);
        $cabeceras->addTextHeader(self::CABECERA, "{$originales} destinatario(s) original(es) sustituido(s)");

        Log::info('correo-interceptado', ['originales' => $originales, 'asunto' => $asunto]);

        return null;
    }

    /** Quita del texto cualquier dirección de correo. */
    public static function enmascarar(string $texto): string
    {
        return (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[correo oculto]', $texto);
    }

    private static function agregarAviso(Email $mensaje, int $originales): void
    {
        $detalle = "{$originales} destinatario(s) original(es) sustituido(s).";

        $html = self::cuerpo($mensaje->getHtmlBody());
        if ($html !== null) {
            $banda = '<div style="background:#FEF3C7;color:#92400E;border:2px solid #F59E0B;padding:10px 14px;'
                . 'margin:0 0 12px;font:bold 14px/1.4 Arial,sans-serif;">' . e(self::AVISO) . ' ' . e($detalle) . '</div>';
            $html = preg_match('/<body[^>]*>/i', $html)
                ? preg_replace('/(<body[^>]*>)/i', '$1' . $banda, $html, 1)
                : $banda . $html;
            $mensaje->html($html, $mensaje->getHtmlCharset() ?? 'utf-8');
        }

        $texto = self::cuerpo($mensaje->getTextBody());
        if ($texto !== null) {
            $mensaje->text(self::AVISO . ' ' . $detalle . "\n\n" . $texto, $mensaje->getTextCharset() ?? 'utf-8');
        }

        if ($html === null && $texto === null) {
            $mensaje->text(self::AVISO . ' ' . $detalle);
        }
    }

    /** @param resource|string|null $cuerpo */
    private static function cuerpo($cuerpo): ?string
    {
        if (is_resource($cuerpo)) {
            $cuerpo = stream_get_contents($cuerpo);
        }

        return is_string($cuerpo) && $cuerpo !== '' ? $cuerpo : null;
    }
}
