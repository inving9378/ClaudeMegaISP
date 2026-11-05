<?php

namespace Tests\Feature\Unificacion;

use App\Console\Commands\Active\UniAplicarMatrizCommand;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UniAplicarMatrizPerfilTest extends TestCase
{
    public function test_perfil_y_rol_supervision_simulan_aplican_y_revierten(): void
    {
        $dir = sys_get_temp_dir().'/uni-test-'.uniqid();
        mkdir($dir);
        $ids = [];
        foreach (['task_view_full_task', 'talento.view', 'otro_perm'] as $n) {
            $ids[$n] = Permission::firstOrCreate(['name' => $n, 'guard_name' => 'web'])->id;
        }
        $mos = Role::firstOrCreate(['name' => 'Mostrador', 'guard_name' => 'web']);
        $sup = Role::firstOrCreate(['name' => 'SUPERVISOR_MOSTRADOR', 'guard_name' => 'web']);
        $uid = DB::table('users')->insertGetId(['name' => 'uni test', 'login_user' => 'uni_t_'.uniqid(), 'email' => uniqid().'@t.local', 'password' => 'x', 'estado' => 'activo']);
        DB::table('model_has_roles')->insert(['role_id' => $mos->id, 'model_type' => 'App\Models\User', 'model_id' => $uid]);
        foreach (['task_view_full_task', 'otro_perm'] as $n) {
            DB::table('model_has_permissions')->insert(['permission_id' => $ids[$n], 'model_type' => 'App\Models\User', 'model_id' => $uid]);
        }

        $this->artisan('uni:aplicar-matriz', ['--perfil' => 'Mostrador', '--rol-supervision' => true, '--dir' => $dir])->assertExitCode(0);
        $this->assertSame(2, DB::table('model_has_permissions')->where('model_id', $uid)->count(), 'dry-run no toca nada');

        $this->artisan('uni:aplicar-matriz', ['--perfil' => 'Mostrador', '--rol-supervision' => true, '--aplicar' => true, '--dir' => $dir]);
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_id', $uid)->count());
        $this->assertTrue(DB::table('role_has_permissions')->where(['role_id' => $sup->id, 'permission_id' => $ids['talento.view']])->exists());

        $rev = glob("$dir/*-revertir.json")[0];
        $this->artisan('uni:aplicar-matriz', ['--revertir' => $rev])->assertExitCode(0);
        $this->assertSame(2, DB::table('model_has_permissions')->where('model_id', $uid)->count());
        $this->assertFalse(DB::table('role_has_permissions')->where(['role_id' => $sup->id, 'permission_id' => $ids['talento.view']])->exists());
        $this->assertContains('talento.view', UniAplicarMatrizCommand::PERMISOS_SUPERVISION);
    }

    public function test_perfil_invalido_se_rechaza(): void
    {
        $this->artisan('uni:aplicar-matriz', ['--perfil' => 'Admin'])->assertExitCode(1);
    }
}
