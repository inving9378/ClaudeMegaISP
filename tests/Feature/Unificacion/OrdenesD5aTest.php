<?php

namespace Tests\Feature\Unificacion;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\MegaFamilia\Controllers\ApiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * UNI-T D5a (#1014): cerrar una orden de Talento por PUT tecnico/ordenes/{id} (MegaFamilia) pasa por la
 * compuerta de evidencias solo con `ordenes_d5a` encendido. Solo contra megaisp_test; todo se revierte.
 */
class OrdenesD5aTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
    }

    /** Orden de Talento en curso, asignada a un técnico con perfil de colaborador. */
    private function orden(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'UNI-PRUEBA', 'father_last_name' => 'D5a', 'email' => uniqid('d5a') . '@example.test',
            'login_user' => uniqid('d5a'), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Tipo de orden con una evidencia obligatoria (propios, se revierten con la transacción).
        $typeId = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'UNI-PRUEBA D5a', 'category' => 'interna', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $evId = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'UNI-PRUEBA fachada', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_ot_type_evidence_requirements')->insert([
            'ot_type_id' => $typeId, 'evidence_type_id' => $evId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $task = new Task();
        $task->forceFill([
            'title' => 'UNI-PRUEBA D5a ' . uniqid(), 'status' => 'InProgress', 'tipo' => 'interna', 'talento_type_id' => $typeId,
        ])->save();
        $task->users()->attach($userId);

        return [User::find($userId), $task];
    }

    private function cerrar(User $u, Task $t): \Illuminate\Http\JsonResponse
    {
        Auth::login($u);
        $req = Request::create('/x', 'PUT', ['status' => 'completada']);

        return app(ApiController::class)->updateTecnicoOrden($req, $t->id);
    }

    public function test_con_interruptor_on_orden_sin_evidencias_no_cierra(): void
    {
        config(['unificacion.interruptores.ordenes_d5a' => true]);
        [$u, $t] = $this->orden();

        $res = $this->cerrar($u, $t);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame('InProgress', $t->fresh()->status);
    }

    public function test_con_interruptor_off_cierra_como_antes(): void
    {
        config(['unificacion.interruptores.ordenes_d5a' => false]);
        [$u, $t] = $this->orden();

        $res = $this->cerrar($u, $t);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('Done', $t->fresh()->status);
    }

    public function test_con_interruptor_on_tarea_comun_sin_tipo_talento_sigue_cerrando(): void
    {
        config(['unificacion.interruptores.ordenes_d5a' => true]);
        [$u, $t] = $this->orden();
        $t->talento_type_id = null;
        $t->save();

        $res = $this->cerrar($u, $t);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('Done', $t->fresh()->status);
    }
}
