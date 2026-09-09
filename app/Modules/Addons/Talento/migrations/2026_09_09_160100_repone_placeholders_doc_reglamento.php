<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9990651. La migración 2026_09_09_150000_seed_talento_document_template_
 * firma_placeholders_version (#9990653) creó una versión nueva del Reglamento leyendo el
 * .html del disco EN ESE MOMENTO — que en el checkout donde corrió todavía no traía los
 * placeholders {{doc.*}} de #9990647 (esa rama nunca había mergeado a main). Resultado:
 * la versión vigente (current_version_id) tiene {{firma.*}} pero PERDIÓ {{doc.*}} (volvió a
 * los blancos "____" fijos) — el propio archivo .html en disco YA tiene ambos combinados
 * (ver el merge del cherry-pick de #9990647 en esta misma rama), pero nadie había vuelto a
 * generar una versión de BD a partir de él.
 *
 * Repone una versión nueva con AMBOS placeholders (el archivo ya los trae juntos). Idempotente:
 * si la versión vigente ya tiene '{{doc.lugar_fecha}}', no crea otra.
 */
return new class extends Migration
{
    private const NAME = 'Reglamento Interior de Trabajo';

    private const RESOURCE_FILE = __DIR__.'/../resources/document-templates-personal/03-reglamento-interior-trabajo.html';

    public function up(): void
    {
        $template = TalentoDocumentTemplate::withTrashed()->where('name', self::NAME)->first();
        if (!$template) {
            return;
        }

        $currentContent = $template->currentVersion?->content ?? '';
        if (str_contains($currentContent, '{{doc.lugar_fecha}}')) {
            return;
        }

        $content = file_get_contents(self::RESOURCE_FILE);

        (new TemplateVersionService())->createVersion(
            $template,
            $content,
            null,
            'Item #9990651: repone {{doc.*}} perdidos por #9990653 (leyó el .html antes del merge de #9990647), junto con {{firma.*}} ya vigente'
        );
    }

    public function down(): void
    {
        // Versionado inmutable a propósito (ver TemplateVersionService): no se borran versiones.
    }
};
