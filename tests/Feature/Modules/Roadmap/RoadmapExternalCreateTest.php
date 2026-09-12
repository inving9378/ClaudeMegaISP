<?php

namespace Tests\Feature\Modules\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\RoadmapItemReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesApplication;

/**
 * CIRC-05 pieza D (#9990949, PASO 7 de #9990888) — pruebas Feature de la API externa de
 * creación de items + hilo de reportes, contra los endpoints reales de
 * app/Modules/Addons/Roadmap/Controllers/RoadmapExternalController.php.
 *
 * USA `Illuminate\Foundation\Testing\TestCase` + `CreatesApplication`/`DatabaseTransactions`
 * (NO `Tests\TestCase`, que corre `migrate:fresh --seed` en CADA test — carísimo e innecesario
 * aquí, mismo patrón que tests/Feature/Roadmap/SubItemCommandDependeDeTest.php).
 */
class RoadmapExternalCreateTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const CREATE_TOKEN = 'test-create-tok-9990949';
    private const WRITE_TOKEN = 'test-write-tok-9990949';
    private const READ_TOKEN = 'test-read-tok-9990949';

    protected function setUp(): void
    {
        parent::setUp();

        // El .env de esta máquina exporta CACHE_DRIVER=file a nivel de shell, lo que PISA el
        // <env name="CACHE_DRIVER" value="array"/> de phpunit.xml (phpunit no sobreescribe una
        // env var de proceso ya exportada). Con cache de archivo, el contador del rate limiter
        // (throttle:N,1 — misma clave sha1(dominio|ip) para TODAS las rutas de este grupo, ver
        // ThrottleRequests::resolveRequestSignature()) sobrevive entre tests y ensucia el caso 6.
        // Se limpia aquí (aislado a storage/framework/cache/data de ESTE worktree, no al de
        // /var/www/megaisp) para que cada test arranque con la cuota completa.
        Cache::flush();

        // Tokens DISTINTOS a propósito: create_token cae a write_token si no se define
        // explícito (config/roadmap_externo.php), así que hay que fijar los tres para que la
        // separación de scopes (casos 3 y 4) sea real y no un falso positivo por el fallback.
        config([
            'roadmap_externo.create_token' => self::CREATE_TOKEN,
            'roadmap_externo.write_token' => self::WRITE_TOKEN,
            'roadmap_externo.read_token' => self::READ_TOKEN,
        ]);
    }

    /** Caso 1 — creación válida: 201, nace pendiente_revision/pending (RoadmapIntakeService::crear()). */
    public function test_creacion_valida_devuelve_201_y_nace_pendiente_revision(): void
    {
        $resp = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item', [
            'title' => 'Item de prueba CIRC-05 pieza D '.uniqid(),
            'description' => 'Descripción de prueba',
        ]);

        $resp->assertStatus(201)->assertJson(['ok' => true]);

        $id = $resp->json('item.id');
        $this->assertDatabaseHas('roadmap_items', [
            'id' => $id,
            'estado_aprobacion' => 'pendiente_revision',
            'status' => 'pending',
        ]);
    }

    /**
     * Caso 2 — estado_aprobacion / excluir_pool_automatico en el body de creación se IGNORAN.
     * Confirmado en el código: `writeNewItem()` valida con `Validator::make($request->all(),
     * $rules)->validated()`, y ninguno de los dos campos está en `$rules` → Laravel los descarta
     * EN SILENCIO del array validado (no dispara 422).
     *
     * Decisión (regla de oro): eso YA satisface el "deben ignorarse/rechazarse" del spec del
     * item — el comportamiento real y ya vivo en dev no es un 422 explícito sino un descarte
     * silencioso de campos fuera de la allowlist, y es aditivo/reversible endurecerlo a un 422
     * si algún día se decide lo contrario. El test verifica el RESULTADO (el item nace con los
     * defaults de siempre, sin que el body pueda fijarlos), no la forma del rechazo.
     */
    public function test_estado_aprobacion_y_excluir_pool_automatico_en_body_se_ignoran(): void
    {
        $resp = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item', [
            'title' => 'Item intento de bypass '.uniqid(),
            'estado_aprobacion' => 'aprobado_claude',
            'excluir_pool_automatico' => true,
        ]);

        $resp->assertStatus(201);

        $id = $resp->json('item.id');
        $this->assertDatabaseHas('roadmap_items', [
            'id' => $id,
            'estado_aprobacion' => 'pendiente_revision',
            'excluir_pool_automatico' => 0,
        ]);
    }

    /** Caso 3 — el token de WRITE no sirve para crear (create_token es un scope aparte). */
    public function test_write_token_no_puede_crear_item(): void
    {
        $resp = $this->postJson('/api/roadmap-externo/'.self::WRITE_TOKEN.'/item', [
            'title' => 'No debería crearse '.uniqid(),
        ]);

        $resp->assertStatus(403);
    }

    /** Caso 4 — el token de CREATE no sirve para actualizar (updateItem exige write_token). */
    public function test_create_token_no_puede_actualizar_item(): void
    {
        $item = RoadmapItem::create([
            'title' => 'Item existente '.uniqid(),
            'estado_aprobacion' => 'pendiente_revision',
        ]);

        $resp = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item/'.$item->id, [
            'comentarios_claude' => 'intento con token equivocado',
        ]);

        $resp->assertStatus(403);
    }

    /**
     * Caso 5 — hilo de reportes: cada POST /reporte agrega, nunca pisa; ambos mensajes aparecen
     * en el historial (append-only, tabla roadmap_item_reports).
     */
    public function test_mensajes_al_hilo_se_acumulan_sin_pisarse(): void
    {
        $item = RoadmapItem::create([
            'title' => 'Item con hilo '.uniqid(),
            'estado_aprobacion' => 'pendiente_revision',
        ]);

        $r1 = $this->postJson("/api/roadmap-externo/".self::CREATE_TOKEN."/item/{$item->id}/reporte", [
            'tipo' => 'nota',
            'resumen' => 'Primer mensaje del hilo',
        ]);
        $r1->assertStatus(201);

        $r2 = $this->postJson("/api/roadmap-externo/".self::CREATE_TOKEN."/item/{$item->id}/reporte", [
            'tipo' => 'nota',
            'resumen' => 'Segundo mensaje del hilo',
        ]);
        $r2->assertStatus(201);

        $this->assertSame(2, RoadmapItemReport::where('roadmap_item_id', $item->id)->count());

        $hist = $this->getJson("/api/roadmap-externo/".self::READ_TOKEN."/item/{$item->id}/historial");
        $hist->assertStatus(200);

        $resumenes = collect($hist->json('reportes'))->pluck('resumen')->all();
        $this->assertContains('Primer mensaje del hilo', $resumenes);
        $this->assertContains('Segundo mensaje del hilo', $resumenes);
        $this->assertCount(2, $resumenes);
    }

    /**
     * Caso 6 — rate limit de creación. El límite lo fija `config('roadmap_externo.rate_write')`,
     * pero esa string se hornea en el middleware `throttle:N,1` AL REGISTRAR LAS RUTAS (boot de
     * la app), no en cada request — sobreescribir el config() dentro del test NO cambia el
     * límite ya aplicado a la ruta. Por eso se LEE el valor real vigente (el mismo que ya se usó
     * al montar las rutas en este boot de test, vía config/roadmap_externo.php) y se agota esa
     * cuota exacta, sin hardcodear un número que pueda desincronizarse del .env.
     */
    public function test_rate_limit_de_creacion_devuelve_429_al_agotar_cuota(): void
    {
        $limite = (int) config('roadmap_externo.rate_write', 30);

        for ($i = 0; $i < $limite; $i++) {
            $resp = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item', [
                'title' => 'Rate limit '.$i.' '.uniqid(),
            ]);
            $this->assertSame(201, $resp->getStatusCode(), "Petición #{$i} debía pasar (cuota={$limite})");
        }

        $resp = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item', [
            'title' => 'Debe rechazarse por rate limit '.uniqid(),
        ]);
        $resp->assertStatus(429);
    }

    /**
     * Caso 7 — idempotencia por clave_externa (CIRC-05 pieza A, #9990946, ya mergeada a main:
     * commit 963d6acc). Mismo clave_externa dos veces = un solo item; la segunda llamada
     * responde 200 con ya_existia:true en vez del 201 de alta nueva.
     */
    public function test_misma_clave_externa_no_duplica_y_devuelve_ya_existia(): void
    {
        $clave = 'clave-test-9990949-'.uniqid();

        $r1 = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item', [
            'title' => 'Idempotencia CIRC-05 '.uniqid(),
            'clave_externa' => $clave,
        ]);
        $r1->assertStatus(201);
        $id1 = $r1->json('item.id');

        $r2 = $this->postJson('/api/roadmap-externo/'.self::CREATE_TOKEN.'/item', [
            'title' => 'Idempotencia CIRC-05 (reintento con otro título)',
            'clave_externa' => $clave,
        ]);
        $r2->assertStatus(200);
        $r2->assertJson(['ok' => true, 'ya_existia' => true]);
        $this->assertSame($id1, $r2->json('item.id'));

        $this->assertSame(1, RoadmapItem::where('clave_externa', $clave)->count());
    }
}
