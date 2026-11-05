<?php

namespace Tests\Feature\Notificaciones;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #699: regresión de #694 — marcar notificaciones como leídas nunca debe dar 500.
 * Usuarios y notificaciones sintéticos dentro de una transacción que se revierte.
 */
class LecturaNotificacionesTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Notif', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        $user->assignRole('super-administrator');

        return $user;
    }

    private function notificar(User $user, array $data = [['id' => 1, 'created_at' => '2026-10-24 10:00:00']], bool $leida = false): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => 'App\\Notifications\\StandardNotification', 'notifiable_type' => User::class,
            'notifiable_id' => $user->id, 'data' => json_encode($data), 'read_at' => $leida ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function leidaEn(string $id)
    {
        return DB::table('notifications')->where('id', $id)->value('read_at');
    }

    public function test_propia_no_leida_se_marca(): void
    {
        $u = $this->admin();
        $n = $this->notificar($u);
        $this->actingAs($u)->get("/read-notification/{$n}")->assertRedirect();
        $this->assertNotNull($this->leidaEn($n));
    }

    public function test_ya_leida_es_idempotente(): void
    {
        $u = $this->admin();
        $n = $this->notificar($u, [['id' => 1, 'created_at' => '2026-10-24 10:00:00']], true);
        $antes = $this->leidaEn($n);
        $this->actingAs($u)->get("/read-notification/{$n}")->assertRedirect();
        $this->assertSame($antes, $this->leidaEn($n));
    }

    public function test_inexistente_ajena_y_formato_invalido_dan_404(): void
    {
        $u = $this->admin();
        $otro = $this->admin();
        $ajena = $this->notificar($otro);
        $this->actingAs($u)->get('/read-notification/' . Str::uuid())->assertNotFound();
        $this->actingAs($u)->get("/read-notification/{$ajena}")->assertNotFound();
        $this->assertNull($this->leidaEn($ajena));
        $this->actingAs($u)->get('/read-notification/no-es-un-id-%27--')->assertNotFound();
    }

    public function test_leer_todas_y_conteo(): void
    {
        $u = $this->admin();
        $otro = $this->admin();
        $a = $this->notificar($u);
        $b = $this->notificar($u);
        $ajena = $this->notificar($otro);
        $this->actingAs($u)->get('/notifications/count')->assertOk()->assertJsonStructure(['count', 'notifications']);
        $this->actingAs($u)->get('/read-all-notifications')->assertRedirect();
        $this->assertNotNull($this->leidaEn($a));
        $this->assertNotNull($this->leidaEn($b));
        $this->assertNull($this->leidaEn($ajena));
    }

    public function test_tickets_notifica_valida_e_invalidas(): void
    {
        $u = $this->admin();
        $otro = $this->admin();
        $n = $this->notificar($u, [['id' => 123]]);
        $this->actingAs($u)->get("/tickets/notifica/{$n}")->assertRedirect('/tickets/ver/123');
        $this->assertNotNull($this->leidaEn($n));
        $ajena = $this->notificar($otro, [['id' => 5]]);
        $this->actingAs($u)->get("/tickets/notifica/{$ajena}")->assertNotFound();
        $this->assertNull($this->leidaEn($ajena));
        $this->actingAs($u)->get('/tickets/notifica/' . Str::uuid())->assertNotFound();
        $this->actingAs($u)->get('/tickets/notifica/xx-invalido')->assertNotFound();
    }
}
