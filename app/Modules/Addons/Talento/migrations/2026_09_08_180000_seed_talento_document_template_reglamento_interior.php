<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9990639. Llena el hueco #03 que dejó el seeder original (item #201, migración
 * 2026_09_02_150000_seed_talento_document_templates_personal.php): Irving/David entregaron
 * "REGLAMENTO INTERIOR DE TRABAJO.docx" en /home/meganet/plantillas-rh/ después de esa
 * migración, así que se registra aquí como migración ADITIVA nueva (la #201 original NO se
 * toca), replicando exactamente su mismo patrón (TemplateVersionService::createTemplate,
 * idempotente por name).
 *
 * A diferencia de las demás plantillas 'personal', esta lleva requires_signature=true: es un
 * documento de acuse (el trabajador firma de enterado), no solo informativo.
 */
return new class extends Migration
{
    private const RESOURCE_DIR = __DIR__.'/../resources/document-templates-personal/';

    private const FILE = '03-reglamento-interior-trabajo.html';

    private const NAME = 'Reglamento Interior de Trabajo';

    private const DESCRIPTION = 'Reglamento interior de trabajo de la empresa; acuse de recibido firmado por el trabajador.';

    public function up(): void
    {
        if (TalentoDocumentTemplate::withTrashed()->where('name', self::NAME)->exists()) {
            return;
        }

        $content = file_get_contents(self::RESOURCE_DIR.self::FILE);

        (new TemplateVersionService())->createTemplate(
            [
                'name' => self::NAME,
                'category' => 'personal',
                'description' => self::DESCRIPTION,
                'active' => true,
                'requires_signature' => true,
            ],
            $content,
            null,
            'Conversión inicial desde .docx (item #9990639, llena el hueco #03)'
        );
    }

    public function down(): void
    {
        TalentoDocumentTemplate::where('name', self::NAME)->forceDelete();
    }
};
