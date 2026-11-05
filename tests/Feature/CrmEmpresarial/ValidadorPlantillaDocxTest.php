<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Services\ValidadorPlantillaDocx;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;
use ZipArchive;

class ValidadorPlantillaDocxTest extends TestCase
{
    use CreatesApplication;

    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /** @param array<int,array<int,string>> $parrafos cada párrafo = lista de runs */
    private function docx(array $parrafos): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';
        foreach ($parrafos as $runs) {
            $xml .= '<w:p>';
            foreach ($runs as $r) {
                $xml .= '<w:r><w:t>'.htmlspecialchars($r).'</w:t></w:r>';
            }
            $xml .= '</w:p>';
        }
        $xml .= '</w:body></w:document>';
        $ruta = tempnam(sys_get_temp_dir(), 'docx');
        $this->tmp[] = $ruta;
        $z = new ZipArchive();
        $z->open($ruta, ZipArchive::OVERWRITE);
        $z->addFromString('word/document.xml', $xml);
        $z->close();

        return $ruta;
    }

    public function test_docx_correcto_es_valido(): void
    {
        $r = (new ValidadorPlantillaDocx())->validar($this->docx([['Hola {{cliente.nombre}}'], ['Folio ', '{{documento.folio}}']]));
        $this->assertTrue($r['valido']);
        $this->assertEqualsCanonicalizing(['cliente.nombre', 'documento.folio'], $r['variables']);
    }

    public function test_detecta_placeholder_partido_entre_runs(): void
    {
        $r = (new ValidadorPlantillaDocx())->validar($this->docx([['Hola {{cliente.', 'nombre}}']]));
        $this->assertFalse($r['valido']);
        $this->assertSame('placeholder_partido', $r['errores'][0]['tipo']);
    }

    public function test_detecta_variable_desconocida(): void
    {
        $r = (new ValidadorPlantillaDocx())->validar($this->docx([['{{cliente.nombre}} y {{cliente.inventada}}']]));
        $this->assertFalse($r['valido']);
        $this->assertSame('variable_desconocida', $r['errores'][0]['tipo']);
    }

    public function test_detecta_llaves_mal_formadas(): void
    {
        $r = (new ValidadorPlantillaDocx())->validar($this->docx([['Total {{servicio.precio_mensual']]));
        $this->assertSame('placeholder_malformado', $r['errores'][0]['tipo']);
    }

    public function test_archivo_que_no_es_docx(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'bad');
        $this->tmp[] = $ruta;
        file_put_contents($ruta, 'no soy un zip');
        $r = (new ValidadorPlantillaDocx())->validar($ruta);
        $this->assertFalse($r['valido']);
        $this->assertSame('archivo_invalido', $r['errores'][0]['tipo']);
    }
}
