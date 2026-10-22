<?php

namespace Tests\Unit\Correo;

use App\Listeners\Correo\InterceptarCorreoDev;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notificar;
use Symfony\Component\Mime\Address;
use Tests\CreatesApplication;

/**
 * Interceptación de correo de prueba (config mail.interceptar).
 *
 * Bootea Laravel (para tener el Mailer real y el listener) pero NO usa base de datos ni
 * RefreshDatabase: todo va por el transporte `array` de phpunit.xml, sin salida externa.
 */
class InterceptarCorreoDevTest extends TestCase
{
    use CreatesApplication;

    private const DESTINO = 'pruebas@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
        Mail::purge('array');
    }

    private function enviados()
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages();
    }

    private function correoDePrueba(): Mailable
    {
        return new CorreoDePruebaInterceptacion();
    }

    private function activar(?string $destino = self::DESTINO): void
    {
        config(['mail.interceptar' => ['activo' => true, 'destino' => $destino, 'prefijo' => '[DEV — PRUEBA]']]);
    }

    public function test_apagada_en_produccion_no_toca_el_correo(): void
    {
        config(['mail.interceptar.activo' => false, 'updates.enabled' => true]);

        Mail::to('cliente@real.mx')->send($this->correoDePrueba());

        $enviado = $this->enviados()->sole();
        $correos = array_map(fn (Address $a) => $a->getAddress(), $enviado->getEnvelope()->getRecipients());
        $this->assertEqualsCanonicalizing(['cliente@real.mx', 'copia@real.mx', 'oculta@real.mx'], $correos);
        $this->assertFalse($enviado->getOriginalMessage()->getHeaders()->has(InterceptarCorreoDev::CABECERA));
        $this->assertStringNotContainsString('[DEV — PRUEBA]', $enviado->getOriginalMessage()->getSubject());
    }

    /** Fuera de producción intercepta aunque el .env la apague (la base de dev trae correos reales). */
    public function test_fuera_de_produccion_intercepta_aunque_este_apagada(): void
    {
        config(['mail.interceptar' => ['activo' => false, 'destino' => self::DESTINO, 'prefijo' => '[DEV — PRUEBA]'], 'updates.enabled' => false]);

        Mail::to('cliente@real.mx')->send($this->correoDePrueba());

        $correos = array_map(fn (Address $a) => $a->getAddress(), $this->enviados()->sole()->getEnvelope()->getRecipients());
        $this->assertSame([self::DESTINO], $correos);

        config(['mail.interceptar.destino' => null]);
        Mail::to('cliente@real.mx')->send($this->correoDePrueba());
        $this->assertCount(1, $this->enviados(), 'Sin buzón de pruebas, fuera de producción no sale nada.');
    }

    public function test_activa_deja_un_solo_destinatario_sin_originales(): void
    {
        $this->activar();

        Mail::to('cliente@real.mx')->send($this->correoDePrueba());

        $enviado = $this->enviados()->sole();
        $correos = array_map(fn (Address $a) => $a->getAddress(), $enviado->getEnvelope()->getRecipients());
        $this->assertSame([self::DESTINO], $correos, 'el sobre SMTP solo lleva el destino autorizado');

        $mensaje = $enviado->getOriginalMessage();
        $this->assertSame([self::DESTINO], array_map(fn (Address $a) => $a->getAddress(), $mensaje->getTo()));
        $this->assertSame([], $mensaje->getCc());
        $this->assertSame([], $mensaje->getBcc());
        $this->assertSame([], $mensaje->getReplyTo());

        $crudo = $enviado->toString();
        foreach (['cliente@real.mx', 'copia@real.mx', 'oculta@real.mx', 'responder@real.mx'] as $real) {
            $this->assertStringNotContainsString($real, $crudo, "{$real} no debe aparecer en cabeceras ni cuerpo");
        }

        $this->assertStringStartsWith('[DEV — PRUEBA] ', $mensaje->getSubject());
        $this->assertStringContainsString('[correo oculto]', $mensaje->getSubject());
        $this->assertStringContainsString(InterceptarCorreoDev::AVISO, $mensaje->getHtmlBody());
        $this->assertSame('3 destinatario(s) original(es) sustituido(s)',
            $mensaje->getHeaders()->get(InterceptarCorreoDev::CABECERA)->getBodyAsString());
    }

    public function test_activa_sin_destino_no_sale_nada(): void
    {
        $this->activar(null);
        Mail::to('cliente@real.mx')->send($this->correoDePrueba());
        $this->assertCount(0, $this->enviados());
    }

    public function test_activa_con_destino_invalido_no_sale_nada(): void
    {
        $this->activar('no-es-un-correo');
        Mail::to('cliente@real.mx')->send($this->correoDePrueba());
        $this->assertCount(0, $this->enviados());
    }

    public function test_cubre_notificaciones_por_correo(): void
    {
        $this->activar();

        Notificar::route('mail', 'cliente@real.mx')->notify(new class extends Notification {
            public function via($notifiable)
            {
                return ['mail'];
            }

            public function toMail($notifiable)
            {
                return (new MailMessage())->subject('Tu prefactura — Meganet')->line('Hola');
            }
        });

        $enviado = $this->enviados()->sole();
        $this->assertSame([self::DESTINO],
            array_map(fn (Address $a) => $a->getAddress(), $enviado->getEnvelope()->getRecipients()));
        $this->assertStringStartsWith('[DEV — PRUEBA] ', $enviado->getOriginalMessage()->getSubject());
    }

    public function test_cubre_lo_encolado_y_otros_mailers(): void
    {
        $this->activar();
        // Un mailer con nombre propio, como los que registra Correo::nombreMailer() del Hub.
        config(['mail.mailers.hub_prueba' => ['transport' => 'array']]);

        Mail::to('cliente@real.mx')->queue($this->correoDePrueba());   // QUEUE_CONNECTION=sync
        Mail::mailer('hub_prueba')->to('otro@real.mx')->send($this->correoDePrueba());

        $hub = Mail::mailer('hub_prueba')->getSymfonyTransport()->messages()->sole();
        $this->assertSame([self::DESTINO],
            array_map(fn (Address $a) => $a->getAddress(), $hub->getEnvelope()->getRecipients()));

        $encolado = $this->enviados()->sole();
        $this->assertSame([self::DESTINO],
            array_map(fn (Address $a) => $a->getAddress(), $encolado->getEnvelope()->getRecipients()));
    }

    public function test_no_duplica_el_prefijo(): void
    {
        $this->activar();
        Mail::to('cliente@real.mx')->send(new class extends Mailable {
            public function build()
            {
                return $this->subject('[DEV — PRUEBA] Ya marcado')->html('<p>x</p>');
            }
        });
        $this->assertSame('[DEV — PRUEBA] Ya marcado', $this->enviados()->sole()->getOriginalMessage()->getSubject());
    }
}

/** Mailable con nombre (no anónimo) para poder encolarlo: la cola lo serializa. */
class CorreoDePruebaInterceptacion extends Mailable
{
    public function build()
    {
        return $this->subject('Aviso para cliente@real.mx — Meganet')
            ->cc('copia@real.mx')
            ->bcc('oculta@real.mx')
            ->replyTo('responder@real.mx')
            ->html('<html><body><p>Hola cliente</p></body></html>');
    }
}
