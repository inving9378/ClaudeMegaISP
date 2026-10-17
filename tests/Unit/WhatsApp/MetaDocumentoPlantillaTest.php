<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Addons\WhatsAppAgent\Services\MetaCloudApiService;
use App\Services\Finance\Billing\DocumentoWhatsAppService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

/**
 * FAC-09 (item #516): cuerpo de las peticiones a Meta para el documento con plantilla. Sin BD ni
 * salida externa: Http::fake + preventStrayRequests; el driver se arma sin leer credenciales.
 */
class MetaDocumentoPlantillaTest extends TestCase
{
    use CreatesApplication;

    private function meta(): MetaCloudApiService
    {
        return new class extends MetaCloudApiService {
            public function __construct()
            {
                $this->accessToken = 'TOKEN-PRUEBA';
                $this->phoneNumberId = '123456';
                $this->apiVersion = 'v21.0';
            }
        };
    }

    public function test_sube_el_pdf_como_media_y_manda_la_plantilla_con_encabezado_documento(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'graph.facebook.com/v21.0/123456/media'    => Http::response(['id' => 'MEDIA-1']),
            'graph.facebook.com/v21.0/123456/messages' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        $meta = $this->meta();
        $mediaId = $meta->uploadMedia('%PDF-1.4 x', 'Factura-1.pdf');
        $meta->sendDocumentTemplate('5500000000', 'factura_pagada_documento', 'es_MX', $mediaId, 'Factura-1.pdf', ['Ana', 'noviembre 2026', '$449.00']);

        $this->assertSame('MEDIA-1', $mediaId);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media') && $r->isMultipart()
            && $r->hasHeader('Authorization', 'Bearer TOKEN-PRUEBA'));
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/messages')) {
                return false;
            }
            $t = $r->data()['template'];

            return $r->data()['type'] === 'template' && $t['name'] === 'factura_pagada_documento' && $t['language']['code'] === 'es_MX'
                && $t['components'][0] === ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => ['id' => 'MEDIA-1', 'filename' => 'Factura-1.pdf']]]]
                && array_column($t['components'][1]['parameters'], 'text') === ['Ana', 'noviembre 2026', '$449.00'];
        });
        Http::assertSentCount(2);
    }

    public function test_si_meta_rechaza_la_subida_lanza_error(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400)]);

        $this->expectExceptionMessage('Invalid parameter');
        $this->meta()->uploadMedia('%PDF', 'x.pdf');
    }

    public function test_telefonos_y_mascara(): void
    {
        $this->assertSame('5215512345678', DocumentoWhatsAppService::normalizar('55 1234 5678'));
        $this->assertSame('5215512345678', DocumentoWhatsAppService::normalizar('+52 55 1234 5678'));
        $this->assertSame('5215512345678', DocumentoWhatsAppService::normalizar('5215512345678'));
        $this->assertNull(DocumentoWhatsAppService::normalizar('12345'));
        $this->assertSame('•••••••••5678', DocumentoWhatsAppService::enmascarar('5215512345678'));
    }
}
