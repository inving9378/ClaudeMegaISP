<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplateSignatureSlot;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9990665. #9990650 cerró en falso: su merge solo dejó una nota de bitácora,
 * nunca creó los slots ni los placeholders del bloque "FIRMAS DE LA COMISIÓN MIXTA" del
 * Reglamento (template 13), que seguía con 2 líneas "Firma: ______" crudas.
 *
 * 1) Agrega los 2 slots que faltaban (orden 3/4, después de empresa/trabajador del Acuse ya
 *    seedeados por #9990646 en orden 1/2).
 * 2) Crea una versión nueva del template a partir del .html en disco (ya actualizado en esta
 *    misma rama: las 2 líneas "Firma: ______" del bloque comisión mixta ahora son
 *    {{firma.comision_empresa}}/{{firma.comision_trabajador}}), preservando los {{doc.*}} y
 *    {{firma.empresa}}/{{firma.trabajador}} del acuse que ya traía la versión vigente.
 *
 * Idempotente: firstOrCreate por (template_id, key) para los slots; la versión solo se crea si
 * la vigente todavía no tiene '{{firma.comision_empresa}}'.
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

        $slots = [
            ['key' => 'comision_empresa', 'label' => 'COMISIÓN MIXTA — Por parte de LA EMPRESA', 'firmante_tipo' => 'admin', 'orden' => 3],
            ['key' => 'comision_trabajador', 'label' => 'COMISIÓN MIXTA — Por parte de los trabajadores', 'firmante_tipo' => 'colaborador', 'orden' => 4],
        ];

        foreach ($slots as $slot) {
            TalentoDocumentTemplateSignatureSlot::firstOrCreate(
                ['template_id' => $template->id, 'key' => $slot['key']],
                [
                    'label' => $slot['label'],
                    'firmante_tipo' => $slot['firmante_tipo'],
                    'orden' => $slot['orden'],
                    'requerido' => true,
                ]
            );
        }

        $currentContent = $template->currentVersion?->content ?? '';
        if (str_contains($currentContent, '{{firma.comision_empresa}}')) {
            return;
        }

        $content = file_get_contents(self::RESOURCE_FILE);

        (new TemplateVersionService())->createVersion(
            $template,
            $content,
            null,
            'Item #9990665: sloteadas las 2 firmas de la COMISIÓN MIXTA ({{firma.comision_empresa}}/{{firma.comision_trabajador}}), reemplazando las líneas "Firma: ______" crudas'
        );
    }

    public function down(): void
    {
        // Versionado inmutable a propósito (ver TemplateVersionService): no se borran versiones.
        $template = TalentoDocumentTemplate::withTrashed()->where('name', self::NAME)->first();
        if (!$template) {
            return;
        }

        TalentoDocumentTemplateSignatureSlot::where('template_id', $template->id)
            ->whereIn('key', ['comision_empresa', 'comision_trabajador'])
            ->delete();
    }
};
