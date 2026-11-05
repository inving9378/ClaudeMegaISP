<?php

namespace Tests\Feature\Vendedores;

use App\Models\CutBox;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Http\Middleware\VerifyCsrfToken;
use Tests\CreatesApplication;

/** #700: acceso horizontal a cajas/cortes de Vendedores. Transacción que se revierte. */
class CortesCajaAccesoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(?string $rol, array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => $rol ?? 'sin rol', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $u = User::find($id);
        if ($rol) {
            $this->rolDeCaja($rol);
            $u->assignRole($rol);
        }
        if ($permisos) {
            $u->givePermissionTo($permisos);
        }
        return $u;
    }

    /** Replica de dev: Mostrador y supervisor con alcance de cortes; el supervisor además cierra. */
    private function rolDeCaja(string $rol): void
    {
        $r = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        $perms = match ($rol) {
            'SUPERVISOR_MOSTRADOR' => ['seller_cuts', 'seller_cuts_close_box'],
            'Mostrador' => ['seller_cuts'],
            default => [],
        };
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $r->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function caja(User $dueno): CutBox
    {
        $b = new CutBox();
        $b->user_id = $dueno->id;
        $b->save();
        return $b;
    }

    public function test_dueno_consulta_y_cierra_su_propia_caja(): void
    {
        $dueno = $this->usuario(null, ['seller_view_panel', 'seller_cuts_close_box']);
        $caja = $this->caja($dueno);
        $this->actingAs($dueno)->getJson("/sellers/cuts/get-user-current-box/{$dueno->id}")->assertOk()->assertJsonFragment(['id' => $caja->id]);
        $this->actingAs($dueno)->postJson("/sellers/cuts/close-user-current-box/{$caja->id}")->assertOk();
        $this->assertNotNull($caja->fresh()->end_at);
    }

    public function test_usuario_con_solo_permiso_de_panel_no_ve_caja_ajena(): void
    {
        $dueno = $this->usuario('Mostrador');
        $caja = $this->caja($dueno);
        $otro = $this->usuario(null, ['seller_view_panel']);
        $this->actingAs($otro)->getJson("/sellers/cuts/get-user-current-box/{$dueno->id}")->assertForbidden();
        $this->actingAs($otro)->getJson("/sellers/cuts/box/{$caja->id}")->assertForbidden();
        $this->actingAs($otro)->postJson("/sellers/cuts/{$dueno->id}")->assertForbidden();
    }

    public function test_cierre_ajeno_sin_alcance_de_cortes_es_403_y_no_cierra(): void
    {
        $dueno = $this->usuario('Mostrador');
        $caja = $this->caja($dueno);
        $otro = $this->usuario(null, ['seller_cuts_close_box']);
        $this->actingAs($otro)->postJson("/sellers/cuts/close-user-current-box/{$caja->id}")->assertForbidden();
        $this->assertNull($caja->fresh()->end_at);
    }

    public function test_supervisor_y_admin_siguen_operando_cajas_ajenas(): void
    {
        $dueno = $this->usuario('Mostrador');
        $caja = $this->caja($dueno);
        foreach (['SUPERVISOR_MOSTRADOR', 'super-administrator', 'Mostrador'] as $rol) {
            $u = $this->usuario($rol);
            $this->actingAs($u)->getJson("/sellers/cuts/get-user-current-box/{$dueno->id}")->assertOk();
            $this->actingAs($u)->getJson("/sellers/cuts/box/{$caja->id}")->assertOk();
        }
        $sup = $this->usuario('SUPERVISOR_MOSTRADOR');
        $this->actingAs($sup)->postJson("/sellers/cuts/close-user-current-box/{$caja->id}")->assertOk();
        $this->assertNotNull($caja->fresh()->end_at);
    }

    public function test_ids_inexistentes_dan_404_no_500(): void
    {
        $admin = $this->usuario('super-administrator');
        foreach ([
            ['getJson', '/sellers/cuts/get-user-current-box/999999999'],
            ['getJson', '/sellers/cuts/box/999999999'],
            ['getJson', '/sellers/cuts/get-received-payments-by-box/999999999'],
            ['getJson', '/sellers/cuts/box-pdf/999999999'],
            ['postJson', '/sellers/cuts/close-user-current-box/999999999'],
        ] as [$m, $url]) {
            $this->actingAs($admin)->$m($url)->assertNotFound();
        }
    }
}
