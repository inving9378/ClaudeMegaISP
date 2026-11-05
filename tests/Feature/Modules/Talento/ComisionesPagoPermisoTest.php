<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Controllers\TalentoComisionSellerController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ComisionesPagoPermisoTest extends TestCase
{
    use DatabaseTransactions;

    private function llamar(string $metodo, User $u): ?int
    {
        $this->actingAs($u);
        try {
            app(TalentoComisionSellerController::class)->$metodo(Request::create('/x', 'POST'), 0);
        } catch (HttpException $e) {
            return $e->getStatusCode();
        } catch (\Throwable $e) {
            return null; // pasó el permiso y falló después por datos de prueba
        }
        return null;
    }

    public function test_con_interruptor_solo_ver_da_403_y_registrar_pago_pasa(): void
    {
        config(['unificacion.interruptores.comisiones_pago_b3a' => true]);
        $perm = Permission::firstOrCreate(['name' => 'talento.comisiones.registrar_pago', 'guard_name' => 'web']);
        $ver  = Permission::firstOrCreate(['name' => 'talento.comisiones.view', 'guard_name' => 'web']);

        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo($ver);
        $gestor = User::factory()->create();
        $gestor->givePermissionTo($ver, $perm);

        foreach (['registerPayment', 'collectDebt'] as $m) {
            $this->assertSame(403, $this->llamar($m, $soloVer), "$m con solo ver");
            $this->assertNotSame(403, $this->llamar($m, $gestor), "$m con permiso");
        }
    }

    public function test_sin_interruptor_se_conserva_el_comportamiento_anterior(): void
    {
        config(['unificacion.interruptores.comisiones_pago_b3a' => false]);
        $u = User::factory()->create();
        $this->assertNotSame(403, $this->llamar('registerPayment', $u));
    }
}
