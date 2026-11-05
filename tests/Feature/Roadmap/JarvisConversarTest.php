<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\Roadmap\Models\JarvisConversacion;
use App\Modules\Addons\Roadmap\Models\JarvisMensaje;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisChatService;
use App\Modules\Addons\Roadmap\Services\JarvisResumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

/**
 * #1068 — conversar con Jarvis en la Torre. El resumen sale de la base (nada inventado), cada
 * conversación queda en el historial y la pregunta viaja a la IA con el estado real del circuito.
 * La IA se simula con Http::fake: la prueba no gasta saldo ni depende de la llave.
 */
class JarvisConversarTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const USUARIO = 990001068;

    public function test_el_resumen_cuenta_lo_terminado_desde_la_visita_y_lo_que_se_trabaja(): void
    {
        $terminado = $this->item('Arreglo de ejemplo terminado', 'completado');
        $enCurso   = $this->item('Pantalla de ejemplo en curso', 'en_progreso');

        $svc    = app(JarvisResumenService::class);
        $estado = $svc->estado(now()->subHour());
        $texto  = $svc->resumen($estado);

        $this->assertContains($terminado->id, array_column($estado['terminados']['ejemplos'], 'id'));
        $this->assertContains($enCurso->id, array_column($estado['en_curso']['items'], 'id'));
        $this->assertStringContainsString('Desde tu última visita', $texto);
        $this->assertStringContainsString('Ahora se trabaja en', $texto);
    }

    public function test_la_visita_se_recuerda_y_abrir_sin_preguntar_no_llena_el_historial(): void
    {
        // La IA falla: el resumen sale igual, armado con los mismos datos de la base.
        $this->proveedorDePrueba();
        Http::fake(['ia.example.test/*' => Http::response(['error' => ['message' => 'sin saldo']], 500)]);
        $chat = app(JarvisChatService::class);

        $primera = $chat->abrirConversacionLibre(self::USUARIO);
        $this->assertSame('jarvis', $primera['conversacion']->mensajes->first()->rol);
        $this->assertStringContainsString('Desde tu última visita', $primera['resumen']);
        $this->assertSame(1, DB::table('jarvis_visitas')->where('user_id', self::USUARIO)->count());

        // Se abre otra vez sin haber preguntado nada: se reusa la misma conversación.
        $segunda = $chat->abrirConversacionLibre(self::USUARIO);
        $this->assertSame($primera['conversacion']->id, $segunda['conversacion']->id);
        $this->assertSame(1, $segunda['conversacion']->mensajes()->count());

        // Con una pregunta de por medio, la siguiente apertura ya es una conversación nueva.
        JarvisMensaje::create(['jarvis_conversacion_id' => $segunda['conversacion']->id, 'rol' => 'irving', 'contenido' => '¿qué me espera?']);
        $tercera = $chat->abrirConversacionLibre(self::USUARIO);
        $this->assertNotSame($segunda['conversacion']->id, $tercera['conversacion']->id);

        $historial = $chat->conversacionesRecientes(self::USUARIO);
        $this->assertSame('¿qué me espera?', collect($historial)->firstWhere('id', $segunda['conversacion']->id)['pregunta']);
    }

    public function test_la_pregunta_viaja_con_el_estado_real_y_empieza_por_el_usuario(): void
    {
        $this->proveedorDePrueba();
        Http::fake(['ia.example.test/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Te espera una decisión.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ])]);

        $chat = app(JarvisChatService::class);
        $conv = $chat->abrirConversacionLibre(self::USUARIO)['conversacion'];
        $this->assertSame('Te espera una decisión.', $conv->mensajes->first()->contenido);   // resumen redactado por la IA
        $res  = $chat->enviarMensaje($conv, '¿Qué me espera?');

        $this->assertSame('Te espera una decisión.', $res['assistant']->contenido);
        $this->assertSame(3, JarvisConversacion::find($conv->id)->mensajes()->count());
        Http::assertSent(function ($req) {
            $cuerpo  = $req->data();
            $sistema = is_array($cuerpo['system'] ?? null) ? json_encode($cuerpo['system']) : (string) ($cuerpo['system'] ?? '');

            return ($cuerpo['messages'][0]['role'] ?? null) === 'user'
                && str_contains($sistema, 'ESTADO')
                && str_contains($sistema, 'eso no lo tengo en los datos del circuito')
                && str_contains($sistema, 'sin asteriscos');
        });
    }

    private function proveedorDePrueba(): void
    {
        IAProveedor::query()->update(['activo' => false]);
        IAProveedor::create([
            'nombre' => 'Claude de prueba', 'driver' => 'claude', 'api_key' => 'clave-de-prueba',
            'endpoint_url' => 'https://ia.example.test/v1/messages', 'modelo_default' => 'claude-haiku-5-5',
            'activo' => true,
        ]);
    }

    private function item(string $titulo, string $estado): RoadmapItem
    {
        $item = RoadmapItem::create([
            'title' => $titulo . ' ' . uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'aprobado_irving',
            'priority' => 'media',
        ]);
        // Directo a la tabla: el modelo no deja marcar «completado» sin el cierre real del circuito.
        DB::table('roadmap_items')->where('id', $item->id)->update(['estado_aprobacion' => $estado, 'updated_at' => now()]);

        return $item;
    }
}
