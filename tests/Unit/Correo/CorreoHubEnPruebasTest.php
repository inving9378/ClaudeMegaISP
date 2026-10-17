<?php

namespace Tests\Unit\Correo;

use App\Models\Core\ApiIntegration;
use App\Modules\Addons\Hub\Services\Correo;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesApplication;

/**
 * #531: una cuenta SMTP del Hub nunca manda de verdad desde las pruebas. La BD de pruebas llegó a
 * tener la cuenta real asignada y Correo::enviar() la usaba aunque phpunit ponga MAIL_MAILER=array.
 * Sin base de datos: la integración se arma en memoria y el servidor es ficticio.
 */
class CorreoHubEnPruebasTest extends TestCase
{
    use CreatesApplication;

    private function cuenta(): ApiIntegration
    {
        $i = new ApiIntegration(['provider' => 'smtp', 'name' => 'Cuenta ficticia']);
        $i->config = ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'nadie@example.test',
            'from_address' => 'nadie@example.test', 'from_name' => 'Pruebas'];
        $i->value = 'clave-ficticia';

        return $i;
    }

    public function test_en_pruebas_la_cuenta_del_hub_no_usa_smtp(): void
    {
        $this->assertTrue(app()->runningUnitTests());
        $this->assertSame('array', Correo::configDe($this->cuenta())['transport']);
    }

    public function test_el_correo_se_queda_en_memoria(): void
    {
        config(['mail.mailers.hub_prueba_531' => Correo::configDe($this->cuenta())]);
        Mail::purge('hub_prueba_531');

        Mail::mailer('hub_prueba_531')->raw('Contenido sintético #531', fn ($m) => $m->to('destino@example.test')->subject('[DEV — PRUEBA] #531'));

        $this->assertCount(1, Mail::mailer('hub_prueba_531')->getSymfonyTransport()->messages());
        Mail::purge('hub_prueba_531');
    }
}
