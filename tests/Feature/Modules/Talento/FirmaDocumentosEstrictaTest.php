<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Controllers\TalentoEmployeeDocumentController;
use Database\Seeders\UniPruebaSeeder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** UNI-T B5c: quién puede gestionar/firmar documentos de un colaborador. */
class FirmaDocumentosEstrictaTest extends TestCase
{
    private function puede($user, $colaboradorId): bool
    {
        $this->actingAs($user);
        $m = new \ReflectionMethod(TalentoEmployeeDocumentController::class, 'puedeGestionarDocumentosDe');
        $m->setAccessible(true);

        return $m->invoke(app(TalentoEmployeeDocumentController::class), $colaboradorId);
    }

    public function test_mostrador_no_gestiona_documentos_ajenos_con_interruptor(): void
    {
        $c = (new UniPruebaSeeder())->perfiles();
        Permission::findOrCreate('talento.work_orders.manage');
        Permission::findOrCreate('talento.expediente.documentos.gestionar');
        $mostrador = $c['mostrador']->user;
        $mostrador->givePermissionTo('talento.work_orders.manage');
        $tecnico = $c['tecnico1'];

        config(['unificacion.interruptores.firma_documentos_estricta' => false]);
        $this->assertTrue($this->puede($mostrador, $tecnico->id), 'comportamiento actual intacto');

        config(['unificacion.interruptores.firma_documentos_estricta' => true]);
        $this->assertFalse($this->puede($mostrador, $tecnico->id), 'work_orders.manage ya no abre documentos');
        $this->assertTrue($this->puede($tecnico->user, $tecnico->id), 'el trabajador gestiona lo suyo');

        $mostrador->givePermissionTo('talento.expediente.documentos.gestionar');
        $this->assertTrue($this->puede($mostrador->fresh(), $tecnico->id), 'RH/administración con permiso de documentos');
    }
}
