<?php

namespace App\Modules\Addons\Hub\Services;

use App\Models\Core\ApiIntegration;
use App\Models\EmailSetting;
use App\Modules\Addons\Hub\Jobs\EnviarCorreoJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Correo por módulo. Con una integración SMTP asignada en "Módulos de
 * servicios", el módulo manda con esa cuenta (servidor, usuario y remitente);
 * sin asignación, exactamente lo de siempre.
 *
 *   Correo::enviar('flotas.correo', $email, new FleetDocumentAlertMail(...));   // Mailables
 *   Correo::mailer('flotas.correo')->raw($texto, fn ($m) => ...);              // raw / html
 *   (new MailMessage)->mailer(Correo::nombreMailer('tickets.correo'))          // notificaciones
 *
 * El mailer se arma en el momento de enviar, con la llave vigente: cambiar la
 * contraseña en Integraciones aplica de inmediato, también en las colas.
 */
class Correo
{
    /** Firma de la configuración con la que se registró cada mailer en este proceso. */
    private static array $firmas = [];

    /** Mailer del módulo: el de su cuenta asignada o el de siempre. */
    public static function mailer(string $clave): Mailer
    {
        return Mail::mailer(self::nombreMailer($clave));
    }

    /** Nombre del mailer registrado en ESTE proceso con la cuenta asignada; null = el de siempre. */
    public static function nombreMailer(string $clave): ?string
    {
        $integracion = Servicios::integracion($clave);
        if (!$integracion) {
            return null;
        }

        $nombre = 'hub_' . $integracion->id;
        $config = self::configDe($integracion);
        $firma = md5(serialize($config));

        // Si la cuenta cambió (contraseña, servidor…) se descarta el mailer viejo;
        // si no, se reusa (y su conexión) para no reconectar en cada correo.
        if ((self::$firmas[$nombre] ?? null) !== $firma) {
            config(["mail.mailers.{$nombre}" => $config]);
            Mail::purge($nombre);
            self::$firmas[$nombre] = $firma;
        }

        return $nombre;
    }

    /**
     * Envía un Mailable con la cuenta del módulo. Sin asignación es exactamente
     * el Mail::to()->send() (o Mail::send()) de siempre.
     *
     * @param mixed $para destinatario(s); null si el Mailable ya trae el suyo
     */
    public static function enviar(string $clave, $para, Mailable $mailable): void
    {
        if (!Servicios::integracion($clave)) {
            $para ? Mail::to($para)->send($mailable) : Mail::send($mailable);
            return;
        }

        if ($mailable instanceof ShouldQueue) {
            EnviarCorreoJob::dispatch($clave, $para, $mailable);
            return;
        }

        self::enviarAhora($clave, $para, $mailable);
    }

    /** Envío inmediato con la cuenta asignada (lo usa también EnviarCorreoJob). */
    public static function enviarAhora(string $clave, $para, Mailable $mailable): void
    {
        // Varios Mailables fijan como remitente el del .env al armarse; el
        // primero que se fija es el que vale, así que va antes el de la cuenta.
        if ($remitente = self::remitente($clave)) {
            $mailable->from($remitente['address'], $remitente['name']);
        }
        if ($para) {
            $mailable->to($para);
        }
        // Mailable::send() con un Mailer concreto envía en este momento.
        $mailable->send(self::mailer($clave));
    }

    /** Remitente de la cuenta asignada, para mensajes que fijan su propio "from". Null = el de siempre. */
    public static function remitente(string $clave): ?array
    {
        $integracion = Servicios::integracion($clave);
        return $integracion ? self::configDe($integracion)['from'] : null;
    }

    /**
     * Encola un Mailable con la cuenta del módulo. Sin asignación es el
     * Mail::to()->queue() de siempre; con asignación, el job arma el mailer al
     * ejecutarse (un mailer registrado aquí no existiría en el worker).
     */
    public static function encolar(string $clave, $para, Mailable $mailable): void
    {
        if (!Servicios::integracion($clave)) {
            Mail::to($para)->queue($mailable);
            return;
        }
        EnviarCorreoJob::dispatch($clave, $para, $mailable);
    }

    /** Remitente para una notificación (MailMessage): el de la cuenta o el de siempre. */
    public static function remitenteDireccion(string $clave): ?string
    {
        return self::remitente($clave)['address'] ?? config('mail.from.address');
    }

    /** Configuración de mailer Laravel a partir de una integración SMTP del Hub. */
    public static function configDe(ApiIntegration $integracion): array
    {
        $c = (array) $integracion->config;
        $cifrado = $c['encryption'] ?? 'tls';

        return [
            // #531: dentro de las pruebas (phpunit) una cuenta del Hub nunca manda de verdad: el
            // correo se queda en memoria, igual que con MAIL_MAILER=array.
            'transport'  => app()->runningUnitTests() ? 'array' : 'smtp',
            'host'       => $c['host'] ?? null,
            'port'       => (int) ($c['port'] ?? 587),
            'encryption' => in_array($cifrado, ['tls', 'ssl'], true) ? $cifrado : null,
            'username'   => $c['username'] ?? null,
            'password'   => $integracion->value,
            'timeout'    => 30,
            'from'       => [
                'address' => ($c['from_address'] ?? null) ?: config('mail.from.address'),
                'name'    => ($c['from_name'] ?? null) ?: config('mail.from.name'),
            ],
        ];
    }

    /** Lo de siempre para un módulo: el servidor del .env o el de Configuración → Correo. */
    public static function configPorDefecto(string $defecto): ?array
    {
        if ($defecto === 'email_settings') {
            $s = EmailSetting::first();
            return $s ? [
                'transport'  => $s->mail_mailer ?: 'smtp',
                'host'       => $s->mail_host,
                'port'       => (int) $s->mail_port,
                'encryption' => $s->mail_encryption ?: null,
                'username'   => $s->mail_username,
                'password'   => $s->mail_password,
                'timeout'    => 30,
            ] : null;
        }

        $nombre = config('mail.default');
        return config("mail.mailers.{$nombre}");
    }

    /**
     * Prueba una configuración: conecta y se autentica con el servidor, SIN
     * mandar ningún correo.
     *
     * @return array{0:bool, 1:string}
     */
    public static function probarConfig(?array $config): array
    {
        if (!$config) {
            return [false, 'No hay servidor de correo configurado.'];
        }
        if (($config['transport'] ?? 'smtp') !== 'smtp') {
            return [false, "El servidor usa «{$config['transport']}»: no manda correos reales (solo los registra)."];
        }
        if (empty($config['host'])) {
            return [false, 'Falta el servidor (host).'];
        }

        $nombre = 'hub_prueba_' . uniqid();
        config(["mail.mailers.{$nombre}" => $config]);
        try {
            $transporte = Mail::mailer($nombre)->getSymfonyTransport();
            if (method_exists($transporte, 'start')) {
                $transporte->start();
                $transporte->stop();
            }
            return [true, "Conectó y se autenticó con {$config['host']}."];
        } catch (\Throwable $e) {
            return [false, self::explicarError($e->getMessage())];
        } finally {
            Mail::purge($nombre);
            config(["mail.mailers.{$nombre}" => null]);
        }
    }

    /** Motivo legible de un error SMTP. */
    public static function explicarError(string $error): string
    {
        $casos = [
            '/authenticat|535|534|username and password|credentials/i' => 'El usuario o la contraseña no son correctos (o la cuenta pide una contraseña de aplicación).',
            '/connection could not be established|connection refused|timed? ?out|getaddrinfo|could not resolve/i' => 'No se pudo conectar con el servidor: revisa el host, el puerto y que el servidor esté accesible.',
            '/ssl|tls|certificate|crypto/i' => 'Falló la conexión segura: revisa el cifrado (TLS/SSL) y el puerto.',
        ];
        foreach ($casos as $patron => $texto) {
            if (preg_match($patron, $error)) {
                return $texto . ' Detalle: ' . mb_substr($error, 0, 200);
            }
        }
        return 'El servidor de correo devolvió un error: ' . mb_substr($error, 0, 200);
    }
}
