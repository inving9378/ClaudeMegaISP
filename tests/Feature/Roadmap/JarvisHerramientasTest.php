<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisHerramientas\Redactor;
use App\Modules\Addons\Roadmap\Services\JarvisHerramientas\RegistroJarvis;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** #1106 — herramientas de solo lectura de Jarvis y su frontera de datos. */
class JarvisHerramientasTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_el_registro_no_expone_llaves_integraciones_ni_clientes(): void
    {
        $r = app(RegistroJarvis::class);
        $nombres = implode(' ', $r->nombres());
        $fuentes = implode(' ', RegistroJarvis::FUENTES);
        foreach (['api_integrations', 'ia_proveedores', 'client_main_information', 'clients', 'env', 'password', 'llave', 'cliente'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $nombres . ' ' . $fuentes);
        }
        // Ninguna herramienta escribe.
        foreach ($r->nombres() as $n) {
            $this->assertDoesNotMatchRegularExpression('/pausar|aprobar|mergear|crear|borrar|escribir/', $n);
        }
        $codigo = file_get_contents((new \ReflectionClass(RegistroJarvis::class))->getFileName());
        foreach (['api_integrations', 'ia_proveedores', 'client_main_information', '->update(', '->delete(', '->insert('] as $x) {
            $this->assertStringNotContainsString($x, $codigo);
        }
    }

    public function test_un_token_falso_en_el_comentario_sale_redactado(): void
    {
        $item = RoadmapItem::create(['title' => 'Item de prueba jarvis ' . uniqid(), 'status' => 'pending', 'estado_aprobacion' => 'aprobado_irving', 'priority' => 'media']);
        $item->forceFill(['comentarios_claude' => 'usar Bearer abcdef1234567890XYZ y password=superSecreto1 y ' . 'sk' . '-ant-api03-FALSOFALSOFALSO1234 y 012345678901234567'])->saveQuietly();

        $out = app(RegistroJarvis::class)->ejecutar('item', ['id' => $item->id]);

        foreach (['abcdef1234567890XYZ', 'superSecreto1', 'FALSOFALSOFALSO1234', '012345678901234567'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $out);
        }
        $this->assertStringContainsString((string) $item->id, $out);
    }

    public function test_item_inexistente_dice_no_lo_se(): void
    {
        $out = json_decode(app(RegistroJarvis::class)->ejecutar('item', ['id' => 999999999]), true);
        $this->assertTrue($out['no_lo_se']);
    }

    public function test_preguntar_por_una_llave_o_un_cliente_no_devuelve_el_dato(): void
    {
        $r = app(RegistroJarvis::class);
        foreach (['llaves_api', 'buscar_cliente', 'integraciones'] as $n) {
            $o = json_decode($r->ejecutar($n, ['nombre' => 'Juan']), true);
            $this->assertTrue($o['no_lo_se']);
        }
        $this->assertSame('[oculto] y [oculto]', Redactor::texto('55 1234 5678 y a@b.com'));
    }

    public function test_las_herramientas_de_lectura_responden_sin_romper(): void
    {
        $r = app(RegistroJarvis::class);
        foreach ($r->nombres() as $n) {
            $o = json_decode($r->ejecutar($n, $n === 'items_por_modulo' ? ['modulo' => 'Roadmap'] : []), true);
            $this->assertIsArray($o, $n);
        }
    }
}
