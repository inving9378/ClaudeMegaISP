<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Models\DocumentoEmision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use LogicException;
use Tests\CreatesApplication;

class DocumentoEmisionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function emision(): DocumentoEmision
    {
        $docx = hash('sha256', 'docx-ficticio');
        return DocumentoEmision::create([
            'folio' => 'TEST-' . uniqid(),
            'plantilla_version_id' => 1,
            'entidad_tipo' => 'crm_expediente',
            'entidad_id' => 1,
            'datos_snapshot' => ['cliente' => 'EMPRESA FICTICIA SA DE CV'],
            'docx_path' => 'x.docx', 'docx_sha256' => $docx,
            'pdf_path' => 'x.pdf', 'pdf_sha256' => hash('sha256', 'pdf-ficticio'),
        ]);
    }

    public function test_se_crea_con_uuid_y_sha256(): void
    {
        $e = $this->emision()->fresh();
        $this->assertNotEmpty($e->uuid);
        $this->assertSame(64, strlen($e->docx_sha256));
        $this->assertSame('EMPRESA FICTICIA SA DE CV', $e->datos_snapshot['cliente']);
    }

    public function test_no_se_puede_actualizar(): void
    {
        $e = $this->emision();
        $this->expectException(LogicException::class);
        $e->update(['folio' => 'OTRO']);
    }

    public function test_no_se_puede_borrar(): void
    {
        $e = $this->emision();
        $this->expectException(LogicException::class);
        $e->delete();
    }

    public function test_update_y_delete_masivos_tambien_se_bloquean(): void
    {
        $this->emision();
        foreach ([fn () => DocumentoEmision::query()->update(['folio' => 'X']), fn () => DocumentoEmision::query()->delete()] as $op) {
            try { $op(); $this->fail('Debió bloquear.'); } catch (LogicException $e) { $this->assertTrue(true); }
        }
    }
}
