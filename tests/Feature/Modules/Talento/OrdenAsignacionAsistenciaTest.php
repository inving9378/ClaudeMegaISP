<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1324: «Nueva orden» muestra quiénes marcaron asistencia hoy. Datos sintéticos; correr SOLO
 * por nombre (megaisp_test, transacción que se deshace).
 */
class OrdenAsignacionAsistenciaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['talento.work_orders.view', 'talento.work_orders.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Solo cuentan las marcas sintéticas de esta prueba.
        DB::table('talento_attendances')->whereDate('check_in_at', today())->delete();
    }

    private function usuario(array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => Str::random(6), 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        $permisos && $user->givePermissionTo($permisos);

        return $user;
    }

    private function colaborador(?int $supervisorId = null): TalentoColaborador
    {
        return TalentoColaborador::create([
            'user_id' => $this->usuario()->id, 'type' => 'interno', 'status' => 'active', 'supervisor_id' => $supervisorId,
        ]);
    }

    private function marca(TalentoColaborador $c, string $entrada, ?string $salida = null): void
    {
        DB::table('talento_attendances')->insert([
            'colaborador_id' => $c->id, 'check_in_at' => today()->setTimeFromTimeString($entrada),
            'check_out_at' => $salida ? today()->setTimeFromTimeString($salida) : null,
            'day_type' => 'worked', 'status' => $salida ? 'closed' : 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_quien_gestiona_ve_a_quienes_marcaron_hoy_en_orden_de_llegada(): void
    {
        $tarde = $this->colaborador();
        $temprano = $this->colaborador();
        $yaSalio = $this->colaborador();
        $sinMarca = $this->colaborador();
        $this->marca($tarde, '09:30');
        $this->marca($temprano, '07:45');
        $this->marca($yaSalio, '08:00', '12:00');
        DB::table('talento_attendances')->insert([
            'colaborador_id' => $sinMarca->id, 'check_in_at' => today()->subDay()->setTime(8, 0),
            'day_type' => 'worked', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->usuario(['talento.work_orders.view', 'talento.work_orders.manage']))
            ->getJson('/talento/api/ordenes/asignacion')
            ->assertOk();

        $presentes = collect($res->json('presentes'));
        // Por hora de llegada; quien ya se fue, al final.
        $this->assertSame([$temprano->id, $tarde->id, $yaSalio->id], $presentes->pluck('id')->all());
        $this->assertSame('07:45', $presentes->firstWhere('id', $temprano->id)['entrada']);
        $this->assertTrue($presentes->firstWhere('id', $yaSalio->id)['salio']);
        $this->assertFalse($presentes->firstWhere('id', $tarde->id)['salio']);
        $this->assertArrayHasKey('name', $presentes->first()['user']);
    }

    public function test_un_supervisor_solo_ve_a_su_equipo(): void
    {
        $supervisorUser = $this->usuario(['talento.work_orders.view']);
        $supervisor = TalentoColaborador::create(['user_id' => $supervisorUser->id, 'type' => 'interno', 'status' => 'active']);
        $suyo = $this->colaborador($supervisor->id);
        $ajeno = $this->colaborador();
        $this->marca($suyo, '08:00');
        $this->marca($ajeno, '08:00');

        $ids = collect($this->actingAs($supervisorUser)->getJson('/talento/api/ordenes/asignacion')->assertOk()->json('presentes'))
            ->pluck('id')->all();

        $this->assertSame([$suyo->id], $ids);
    }
}
