<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\JarvisConversacion;
use App\Modules\Addons\Roadmap\Models\JarvisMensaje;
use App\Modules\Addons\Roadmap\Services\JarvisChatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** #1113 — retomar la última conversación, recientes con título y contexto validado. */
class JarvisHistorialTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const YO = 990001113;
    private const OTRO = 990001114;

    private function conv(int $uid, array $preguntas): JarvisConversacion
    {
        $c = JarvisConversacion::create([
            'sugerencia_clave' => 'conv-t-' . uniqid(), 'categoria' => JarvisChatService::CATEGORIA_CONVERSACION,
            'texto_sugerencia' => 'resumen', 'estado' => 'abierta', 'created_by' => $uid,
        ]);
        JarvisMensaje::create(['jarvis_conversacion_id' => $c->id, 'rol' => 'jarvis', 'contenido' => 'resumen']);
        foreach ($preguntas as $p) {
            JarvisMensaje::create(['jarvis_conversacion_id' => $c->id, 'rol' => 'irving', 'contenido' => $p]);
        }

        return $c;
    }

    public function test_ultima_no_crea_nada_y_es_la_del_propio_usuario(): void
    {
        $chat = app(JarvisChatService::class);
        $antes = JarvisConversacion::count();
        $this->assertNull($chat->ultimaConversacion(self::YO));
        $mia = $this->conv(self::YO, ['hola']);
        $this->conv(self::OTRO, ['ajena']);
        $this->assertSame($mia->id, $chat->ultimaConversacion(self::YO)->id);
        $this->assertSame($antes + 2, JarvisConversacion::count());
    }

    public function test_recientes_maximo_10_con_titulo_y_sin_las_de_otro(): void
    {
        $chat = app(JarvisChatService::class);
        for ($i = 0; $i < 12; $i++) {
            $this->conv(self::YO, ["pregunta número $i"]);
        }
        $this->conv(self::OTRO, ['ajena']);
        $r = $chat->recientesConTitulo(self::YO);
        $this->assertCount(10, $r);
        $this->assertSame('pregunta número 11', $r[0]['titulo']);
        $this->assertNotContains('ajena', array_column($r, 'titulo'));
    }

    public function test_pendiente_es_true_con_pregunta_sin_respuesta(): void
    {
        $chat = app(JarvisChatService::class);
        $c = $this->conv(self::YO, ['¿qué pasó?']);
        $this->assertTrue($chat->tienePendiente($c));
        JarvisMensaje::create(['jarvis_conversacion_id' => $c->id, 'rol' => 'jarvis', 'contenido' => 'esto']);
        $this->assertFalse($chat->tienePendiente($c->fresh()));
    }

    public function test_contexto_invalido_se_ignora_y_el_valido_se_acepta(): void
    {
        $chat = app(JarvisChatService::class);
        $this->assertNull($chat->contextoValido('basura'));
        $this->assertNull($chat->contextoValido(['pestana' => 'inventada', 'item_id' => 999999999]));
        $this->assertStringContainsString('panorama', $chat->contextoValido(['pestana' => 'panorama']));
    }
}
