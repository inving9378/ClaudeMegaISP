<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Controllers\TalentoLoanSettlementController;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PrestamosAutorizarTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(array $permisos): User
    {
        $u = User::forceCreate([
            'name' => 'Prueba', 'login_user' => 'p_' . uniqid(), 'email' => uniqid() . '@prueba.test',
            'password' => 'x', 'estado' => 'activo',
        ]);
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $u->givePermissionTo($p);
        }
        return $u;
    }

    private function prestamo(User $registrador): TalentoLoan
    {
        $col = TalentoColaborador::query()->firstOrFail();
        return TalentoLoan::create([
            'colaborador_id' => $col->id, 'amount' => 100, 'balance' => 100,
            'authorized' => false, 'status' => 'active', 'created_by' => $registrador->id,
        ]);
    }

    private function autorizar(User $u, TalentoLoan $loan, array $body = []): int
    {
        $this->actingAs($u);
        try {
            app(TalentoLoanSettlementController::class)->authorizeLoan(Request::create('/x', 'POST', $body), $loan->id);
            return 200;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $e->getStatusCode();
        }
    }

    public function test_mismo_usuario_registra_y_autoriza_se_rechaza(): void
    {
        config(['unificacion.interruptores.prestamos_autorizar_b3d' => true]);
        $u = $this->usuario(['talento.loans.manage', 'talento.prestamos.autorizar']);
        $this->assertSame(403, $this->autorizar($u, $this->prestamo($u)));
    }

    public function test_otro_usuario_con_permiso_autoriza(): void
    {
        config(['unificacion.interruptores.prestamos_autorizar_b3d' => true]);
        $a = $this->usuario(['talento.loans.manage']);
        $b = $this->usuario(['talento.loans.manage', 'talento.prestamos.autorizar']);
        $this->assertSame(200, $this->autorizar($b, $this->prestamo($a)));
    }

    public function test_sin_permiso_de_autorizar_se_rechaza(): void
    {
        config(['unificacion.interruptores.prestamos_autorizar_b3d' => true]);
        $a = $this->usuario(['talento.loans.manage']);
        $this->assertSame(403, $this->autorizar($a, $this->prestamo($this->usuario([]))));
    }

    public function test_excepcion_exige_motivo(): void
    {
        config(['unificacion.interruptores.prestamos_autorizar_b3d' => true]);
        $u = $this->usuario(['talento.loans.manage', 'talento.prestamos.autorizar', 'talento.prestamos.autorizar_propio']);
        $loan = $this->prestamo($u);
        $this->assertSame(422, $this->autorizar($u, $loan));
        $this->assertSame(200, $this->autorizar($u, $loan, ['motivo' => 'Único gestor disponible']));
    }

    public function test_interruptor_apagado_conserva_comportamiento_actual(): void
    {
        config(['unificacion.interruptores.prestamos_autorizar_b3d' => false]);
        $u = $this->usuario(['talento.loans.manage']);
        $this->assertSame(200, $this->autorizar($u, $this->prestamo($u)));
    }
}
