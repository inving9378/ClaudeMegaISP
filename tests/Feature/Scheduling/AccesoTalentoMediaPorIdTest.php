<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #698: la evidencia de una orden de Talento se sirve por id. Tener talento.media.view no basta:
 * un técnico no puede abrir la evidencia de la orden de OTRO colaborador (403); el dueño y el
 * staff de órdenes sí pasan la autorización (sin archivo en disco responde 404, no 403).
 */
class AccesoTalentoMediaPorIdTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(['check_route_permission']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function colaborador(array $permisos): array
    {
        $uid = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Talento', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cid = DB::table('talento_colaboradores')->insertGetId([
            'user_id' => $uid, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($uid);
        foreach ($permisos as $p) {
            Permission::findOrCreate($p, 'web');
            $user->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return [$user->fresh(), $cid];
    }

    public function test_evidencia_ajena_403_propia_y_staff_pasan(): void
    {
        [$duenio, $cidDuenio] = $this->colaborador(['talento.media.view', 'talento.work_orders.view']);
        [$intruso] = $this->colaborador(['talento.media.view', 'talento.work_orders.view']);
        [$staff] = $this->colaborador(['talento.media.view', 'talento.work_orders.manage']);

        $tipo = DB::table('talento_work_order_types')->value('id');
        $this->assertNotNull($tipo);
        $wo = DB::table('talento_work_orders')->insertGetId([
            'colaborador_id' => $cidDuenio, 'type_id' => $tipo, 'points' => 0, 'is_billable' => 0,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mediaId = DB::table('talento_work_order_media')->insertGetId([
            'work_order_id' => $wo, 'type' => 'presentation', 'file_path' => 'no/existe-698.jpg',
            'created_at' => now(),
        ]);

        $this->actingAs($intruso)->get("/talento/media/$mediaId")->assertStatus(403);
        $this->actingAs($duenio)->get("/talento/media/$mediaId")->assertStatus(404);
        $this->actingAs($staff)->get("/talento/media/$mediaId")->assertStatus(404);
    }
}
