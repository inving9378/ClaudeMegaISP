<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #698: cada usuario solo consulta lo que tiene autorizado. Perfiles: Técnico, Vendedor, Mostrador
 * (sin task_view_full_task), Administrador y usuario con task_view_full_task. Se prueba el acceso
 * por id a una tarea ajena en cada endpoint de Scheduling (404) y que lo legítimo se conserva.
 * El permiso de ruta (check_route_permission) se omite a propósito: aquí se prueba la autorización
 * por objeto. Todo dentro de una transacción que se revierte.
 */
class AccesoTareasPorIdTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(['check_route_permission', \App\Http\Middleware\VerifyCsrfToken::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(?string $rol, array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => $rol ?? 'sin rol', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        if ($rol) {
            Role::findOrCreate($rol, 'web');
            $user->assignRole($rol);
        }
        foreach ($permisos as $p) {
            Permission::findOrCreate($p, 'web');
            $user->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $user->fresh();
    }

    private function tarea(User $asignado): int
    {
        $id = DB::table('tasks')->insertGetId([
            'title' => 'Tarea sintética', 'tipo' => 'interna', 'status' => 'Open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $id, 'user_id' => $asignado->id]);
        return $id;
    }

    /** [método, url] de cada endpoint de Scheduling que recibe el id de una tarea. */
    private function endpoints(int $id): array
    {
        return [
            ['get',  "/scheduling/task/editar/$id"],
            ['post', "/scheduling/task/get-data-task/$id"],
            ['post', "/scheduling/task/update/$id"],
            ['post', "/scheduling/task/destroy/$id"],
            ['post', "/scheduling/task/archive/$id"],
            ['post', "/scheduling/task/unarchive/$id"],
            ['post', "/scheduling/task/add_note/$id"],
            ['post', "/scheduling/task/get-notes-by-task/$id"],
            ['post', "/scheduling/task/get-list-template-verification-by-task/$id"],
            ['post', "/scheduling/task/download-file/$id"],
            ['post', "/scheduling/task/upload-file/$id"],
            ['post', "/scheduling/task/remove-file/$id"],
        ];
    }

    public static function perfilesSinAlcanceNoTocanTareaAjena(): array
    {
        return [['TECNICO'], ['Vendedor'], ['Mostrador']];
    }

    /** @dataProvider perfilesSinAlcanceNoTocanTareaAjena */
    public function test_perfil_sin_alcance_recibe_404_en_todo_id_ajeno(string $rol): void
    {
        $duenio = $this->usuario('TECNICO');
        $id = $this->tarea($duenio);
        $intruso = $this->usuario($rol);

        foreach ($this->endpoints($id) as [$metodo, $url]) {
            $r = $this->actingAs($intruso)->json($metodo, $url); $this->assertContains($r->getStatusCode(), [403, 404], "$rol $metodo $url"); // destroy lo corta antes otra capa con 403
        }

        $this->actingAs($intruso)->json('post', '/scheduling/task/update-task-to-calendar', ['data' => ['id' => $id, 'start' => now()->toDateTimeString(), 'end' => null]])
            ->assertStatus(403); // mover tarea ajena: 403 de #697, conservado
        $this->assertNull(DB::table('tasks')->where('id', $id)->value('deleted_at'), 'la tarea ajena no se borró');
        $this->assertEquals(0, DB::table('tasks')->where('id', $id)->value('archived'));
    }

    public function test_asignado_conserva_acceso_a_su_tarea(): void
    {
        $duenio = $this->usuario('TECNICO');
        $id = $this->tarea($duenio);

        $this->actingAs($duenio)->postJson("/scheduling/task/get-data-task/$id")->assertOk()->assertJsonPath('id', $id);
        $this->actingAs($duenio)->postJson("/scheduling/task/get-notes-by-task/$id")->assertOk();
    }

    public function test_administrador_y_task_view_full_task_conservan_acceso_total(): void
    {
        $duenio = $this->usuario('TECNICO');
        $id = $this->tarea($duenio);

        $admin = $this->usuario('ADMINISTRADOR');
        $superAdmin = $this->usuario('super-administrator');
        $supervisor = $this->usuario('Mostrador', ['task_view_full_task']);

        foreach ([$admin, $superAdmin, $supervisor] as $u) {
            $this->actingAs($u)->postJson("/scheduling/task/get-data-task/$id")->assertOk()->assertJsonPath('id', $id);
            $this->actingAs($u)->postJson("/scheduling/task/get-notes-by-task/$id")->assertOk();
        }
    }

    public function test_tarea_inexistente_es_404(): void
    {
        $u = $this->usuario('ADMINISTRADOR');
        $this->actingAs($u)->postJson('/scheduling/task/get-data-task/999999999')->assertStatus(404);
    }

    public function test_calendario_devuelve_solo_tareas_propias_por_perfil(): void
    {
        $a = $this->usuario('TECNICO');
        $b = $this->usuario('Vendedor');
        $idA = $this->tarea($a);
        $idB = $this->tarea($b);

        $svc = app(\App\Services\TaskService::class);
        $metodo = collect(get_class_methods($svc))->first(fn($m) => str_contains(strtolower($m), 'event'));
        $this->assertNotNull($metodo);

        foreach ([[$a, $idA, $idB], [$b, $idB, $idA]] as [$u, $propia, $ajena]) {
            $this->actingAs($u);
            $ids = collect($svc->$metodo([]))->pluck('id')->all();
            $this->assertContains($propia, $ids);
            $this->assertNotContains($ajena, $ids);
        }

        $this->actingAs($this->usuario('Mostrador', ['task_view_full_task']));
        $ids = collect($svc->$metodo([]))->pluck('id')->all();
        $this->assertContains($idA, $ids);
        $this->assertContains($idB, $ids);
    }
}
