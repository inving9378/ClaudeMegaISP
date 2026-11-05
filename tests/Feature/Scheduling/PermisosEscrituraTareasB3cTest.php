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

/** #952 (B3c): task_view_full_task solo ve; escribir exige permiso propio o tareas.gestionar_ordenes. */
class PermisosEscrituraTareasB3cTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(['check_route_permission', \App\Http\Middleware\VerifyCsrfToken::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(array $permisos): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'B3c', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        foreach ($permisos as $p) {
            Permission::findOrCreate($p, 'web');
            $user->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $user->fresh();
    }

    private function tarea(): int
    {
        return DB::table('tasks')->insertGetId([
            'title' => 'Tarea sintética', 'tipo' => 'interna', 'status' => 'Open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_solo_ver_recibe_403_en_escritura_con_interruptor(): void
    {
        config(['unificacion.interruptores.permisos_tareas_b3c' => true]);
        $id = $this->tarea();
        $ver = $this->usuario(['task_view_full_task']);
        foreach ([["/scheduling/task/update/$id"], ["/scheduling/task/destroy/$id"], ["/scheduling/task/upload-file/$id"], ["/scheduling/task/remove-file/$id"], ['/scheduling/task/add']] as [$url]) {
            $this->actingAs($ver)->postJson($url)->assertStatus(403);
        }
        $this->assertNotNull(DB::table('tasks')->where('id', $id)->first(), 'la tarea sigue existiendo');
    }

    public function test_quien_gestiona_conserva_la_accion(): void
    {
        config(['unificacion.interruptores.permisos_tareas_b3c' => true]);
        $id = $this->tarea();
        $this->actingAs($this->usuario(['task_view_full_task', 'tareas.gestionar_ordenes']))
            ->postJson("/scheduling/task/destroy/$id")->assertOk();
        $id2 = $this->tarea();
        $this->actingAs($this->usuario(['task_view_full_task', 'task_delete_task']))
            ->postJson("/scheduling/task/destroy/$id2")->assertOk();
    }

    public function test_interruptor_apagado_no_cambia_nada(): void
    {
        config(['unificacion.interruptores.permisos_tareas_b3c' => false]);
        $id = $this->tarea();
        $this->actingAs($this->usuario(['task_view_full_task']))
            ->postJson("/scheduling/task/destroy/$id")->assertOk();
    }
}
