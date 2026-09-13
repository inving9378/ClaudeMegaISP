<?php

namespace Tests\Feature\Roadmap;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\RoadmapItemRespuesta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * CIRC-02b PASO 4a+4b (#9991057 + #9991065) — smoke test E2E del mecanismo de respuestas: un
 * comentario de Irving sobre un item `requiere_irving` (POST /api/roadmap/circuito/decidir,
 * RoadmapController::decidir() ~1996-2021) ES la respuesta — se guarda en
 * `roadmap_item_respuestas` (no pisa `comentarios_claude`) y, si NO es `solo_comentario`,
 * re-encola el item: nivel A → aprobado_claude, cualquier otro nivel → aprobado_revisor.
 * `nivel_riesgo` NUNCA se toca.
 *
 * Casos (1), (1b) y (2) — PASO 4a (#9991057): el comentario "ejecutable" re-encola según nivel_riesgo
 * (A vs. cualquier otro), y `solo_comentario=true` deja constancia sin re-encolar.
 * Casos (3), (4) y (5) — PASO 4b (#9991065): `firmaAutomatica()` puro, que `circuito:reportar` no
 * consume respuestas pendientes, y que `circuito:respuesta-prompt` + `decidir()` no tocan
 * `nivel_riesgo=C`.
 *
 * Patrón EXACTO de `CierreHuecoRebotaTest`/`PermisosResolucionTest`: extiende
 * `Illuminate\Foundation\Testing\TestCase` (NO `Tests\TestCase`, que corre `migrate:fresh --seed`)
 * + `CreatesApplication` + `DatabaseTransactions` (rollback automático, no ensucia la BD de dev).
 */
class Circ02bRespuestaMecanismoE2ETest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function itemRequiereIrving(string $nivelRiesgo): RoadmapItem
    {
        return RoadmapItem::create([
            'title'             => 'Item de prueba PASO4a ' . uniqid(),
            'estado_aprobacion' => 'requiere_irving',
            'status'            => 'pending',
            'nivel_riesgo'      => $nivelRiesgo,
        ]);
    }

    private function usuarioConDecidir(): User
    {
        $permiso = Permission::firstOrCreate(['name' => 'circuito.decidir', 'guard_name' => 'web']);

        $user = User::create([
            'name'       => 'Prueba Circ02b PASO4a',
            'login_user' => 'prueba_circ02b_paso4a_' . uniqid(),
        ])->fresh();
        $user->givePermissionTo($permiso);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function decidirComentar(RoadmapItem $item, User $user, bool $soloComentario)
    {
        return $this->actingAs($user)->postJson('/api/roadmap/circuito/decidir', [
            'id'              => $item->id,
            'accion'          => 'comentar',
            'comentario'      => 'Comentario de prueba ' . uniqid(),
            'solo_comentario' => $soloComentario,
        ]);
    }

    /**
     * Caso (1) — comentario "ejecutable" (solo_comentario=false) sobre un item nivel B: crea la
     * respuesta con ejecutar=true y re-encola a aprobado_revisor (no es nivel A).
     */
    public function test_caso_1_comentario_dispara_reencolado_nivel_b(): void
    {
        $item = $this->itemRequiereIrving('B');
        $user = $this->usuarioConDecidir();

        $respuesta = $this->decidirComentar($item, $user, false);

        $respuesta->assertOk();
        $respuesta->assertJson(['ok' => true]);

        $filas = RoadmapItemRespuesta::where('item_id', $item->id)->get();
        $this->assertCount(1, $filas, 'Debe crear exactamente una fila en roadmap_item_respuestas.');
        $this->assertTrue((bool) $filas->first()->ejecutar);
        $this->assertNull($filas->first()->consumida_at);

        $fresco = $item->fresh();
        $this->assertSame('aprobado_revisor', $fresco->estado_aprobacion);
        $this->assertSame('B', $fresco->nivel_riesgo, 'nivel_riesgo nunca se toca por un comentario.');
    }

    /**
     * Caso (1b) — mismo mecanismo, pero nivel A: el destino de re-encolado cambia a aprobado_claude.
     */
    public function test_caso_1b_comentario_dispara_reencolado_nivel_a(): void
    {
        $item = $this->itemRequiereIrving('A');
        $user = $this->usuarioConDecidir();

        $respuesta = $this->decidirComentar($item, $user, false);

        $respuesta->assertOk();

        $fresco = $item->fresh();
        $this->assertSame('aprobado_claude', $fresco->estado_aprobacion);
        $this->assertSame('A', $fresco->nivel_riesgo);
    }

    /**
     * Caso (2) — solo_comentario=true: deja constancia (fila con ejecutar=false) pero NO
     * re-encola; el item sigue requiere_irving.
     */
    public function test_caso_2_solo_comentario_no_reencola(): void
    {
        $item = $this->itemRequiereIrving('B');
        $user = $this->usuarioConDecidir();

        $respuesta = $this->decidirComentar($item, $user, true);

        $respuesta->assertOk();

        $filas = RoadmapItemRespuesta::where('item_id', $item->id)->get();
        $this->assertCount(1, $filas);
        $this->assertFalse((bool) $filas->first()->ejecutar);

        $fresco = $item->fresh();
        $this->assertSame('requiere_irving', $fresco->estado_aprobacion, 'solo_comentario no debe re-encolar.');
        $this->assertSame('B', $fresco->nivel_riesgo);
    }

    /**
     * Caso (3) — `RoadmapItem::firmaAutomatica()` distingue a Irving (nunca automática) de los
     * actores automáticos del circuito. Documenta por inspección que `decidir()` nunca produce el
     * segundo caso vía HTTP (el autor ahí siempre se arma como `irving:...`): no se ejercita por
     * request real, solo se prueba el método puro.
     */
    public function test_firma_automatica_distingue_irving_de_actores_automaticos(): void
    {
        $this->assertFalse(RoadmapItem::firmaAutomatica('irving:algo'));
        $this->assertTrue(RoadmapItem::firmaAutomatica('revisor:algo'));
    }

    /**
     * Caso (4) — `circuito:reportar` muta `comentarios_claude` (espejo legible del historial,
     * `RoadmapReportService::append()`) pero esa mutación NO debe consumir respuestas pendientes:
     * el hook de auto-consumo del modelo solo dispara cuando `estado_aprobacion` cambia a
     * completado/rechazado/cancelado, y `circuito:reportar` nunca toca esa columna.
     */
    public function test_circuito_reportar_no_consume_respuesta_pendiente(): void
    {
        $item = RoadmapItem::create([
            'title'             => 'Item de prueba PASO4b caso4 ' . uniqid(),
            'estado_aprobacion' => 'aprobado_revisor',
            'status'            => 'pending',
        ]);

        $cuerpoOriginal = 'Cuerpo de la respuesta pendiente ' . uniqid();
        $respuesta = RoadmapItemRespuesta::create([
            'item_id'      => $item->id,
            'autor'        => 'irving:prueba',
            'canal'        => 'torre',
            'cuerpo'       => $cuerpoOriginal,
            'ejecutar'     => true,
            'consumida_at' => null,
        ]);

        $exit = Artisan::call('circuito:reportar', [
            'id'        => $item->id,
            '--sid'     => 'wt-test',
            '--tipo'    => 'avance',
            '--resumen' => 'avance de prueba, no debe tocar respuestas pendientes',
        ]);

        $this->assertSame(0, $exit);

        // La mutación sí ocurrió (constatamos el efecto real que se está aislando)...
        $this->assertStringContainsString(
            'avance de prueba, no debe tocar respuestas pendientes',
            (string) $item->fresh()->comentarios_claude
        );

        // ...pero la respuesta pendiente sigue intacta y sin consumir.
        $respuestaFresca = $respuesta->fresh();
        $this->assertNull($respuestaFresca->consumida_at);
        $this->assertSame($cuerpoOriginal, $respuestaFresca->cuerpo);
    }

    /**
     * Caso (5) — con una respuesta sin consumir, `circuito:respuesta-prompt` la imprime con
     * precedencia (exit 0, bloque "RESPUESTA DE IRVING" + cuerpo). Luego, sobre un item
     * `nivel_riesgo=C` `requiere_irving`, `POST /api/roadmap/circuito/decidir` con
     * `accion=comentar` re-encola a `aprobado_revisor` (nivel C nunca sube a `aprobado_claude`,
     * eso es solo para nivel A) SIN tocar `nivel_riesgo` — la regla explícita del controlador.
     */
    public function test_respuesta_prompt_y_decidir_comentar_no_tocan_nivel_riesgo_C(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $permiso = Permission::firstOrCreate(['name' => 'circuito.decidir', 'guard_name' => 'web']);

        $user = User::create([
            'name'       => 'Prueba Circ02b Caso5 ' . uniqid(),
            'login_user' => 'prueba_circ02b_caso5_' . uniqid(),
        ])->fresh();
        $user->givePermissionTo($permiso);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $item = RoadmapItem::create([
            'title'             => 'Item de prueba PASO4b caso5 nivel C ' . uniqid(),
            'estado_aprobacion' => 'requiere_irving',
            'nivel_riesgo'      => 'C',
            'status'            => 'pending',
        ]);

        $cuerpoOriginal = 'Cuerpo de la respuesta pendiente caso5 ' . uniqid();
        RoadmapItemRespuesta::create([
            'item_id'      => $item->id,
            'autor'        => 'irving:prueba',
            'canal'        => 'torre',
            'cuerpo'       => $cuerpoOriginal,
            'ejecutar'     => true,
            'consumida_at' => null,
        ]);

        $exit = Artisan::call('circuito:respuesta-prompt', ['id' => $item->id]);
        $salida = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('RESPUESTA DE IRVING', $salida);
        $this->assertStringContainsString($cuerpoOriginal, $salida);

        $this->actingAs($user)
            ->postJson('/api/roadmap/circuito/decidir', [
                'id'         => $item->id,
                'accion'     => 'comentar',
                'comentario' => 'Comentario de Irving que re-encola ' . uniqid(),
            ])
            ->assertOk();

        $fresco = $item->fresh();
        $this->assertSame('aprobado_revisor', $fresco->estado_aprobacion);
        $this->assertSame('C', $fresco->nivel_riesgo, 'nivel_riesgo NUNCA se toca en decidir() — ver comentario del controlador.');
    }
}
