<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9990647 ("Completar documento"). El Reglamento Interior de Trabajo
 * (item #9990639) tenía 10 blancos "____" fijos en el HTML, sin placeholder. 4 de ellos son
 * el bloque de firma del acuse individual (POR LA EMPRESA / PERSONA TRABAJADORA + las líneas
 * "Firma:" de la Comisión Mixta) — esos quedan tal cual, son terreno de #9990646 (firmas
 * múltiples con rol). Los otros 6 (domicilio del centro de trabajo + nombre/cargo de cada
 * integrante de la Comisión Mixta + lugar y fecha) NO son atributos del colaborador — son
 * doc-específicos, se capturan una vez por documento vía "Completar documento" y se guardan en
 * `talento_employee_documents.datos_extra` (inyectados como `doc.*`, ver
 * EmployeeDocumentPackageService::buildData).
 *
 * Aditiva e idempotente: si el catálogo ya está declarado, no lo pisa; si la versión vigente ya
 * trae los placeholders `{{doc.*}}` (p.ej. entorno fresco que sembró el archivo .html ya
 * corregido), no crea una versión nueva — el versionado de plantillas es inmutable
 * (TemplateVersionService::createVersion siempre inserta, nunca actualiza).
 */
return new class extends Migration
{
    private const NAME = 'Reglamento Interior de Trabajo';

    private const RESOURCE_FILE = __DIR__.'/../resources/document-templates-personal/03-reglamento-interior-trabajo.html';

    private const FILLABLE_FIELDS = [
        ['key' => 'domicilio_centro_trabajo', 'label' => 'Domicilio del centro de trabajo', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'comision_empresa_nombre', 'label' => 'Comisión mixta — Nombre (por la empresa)', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'comision_empresa_cargo', 'label' => 'Comisión mixta — Cargo (por la empresa)', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'comision_trabajador_nombre', 'label' => 'Comisión mixta — Nombre (por los trabajadores)', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'comision_trabajador_cargo', 'label' => 'Comisión mixta — Cargo (por los trabajadores)', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'lugar_fecha', 'label' => 'Lugar y fecha', 'group' => 'doc', 'type' => 'text'],
    ];

    public function up(): void
    {
        $template = TalentoDocumentTemplate::with('currentVersion')->where('name', self::NAME)->first();
        if (!$template) {
            return;
        }

        if (empty($template->fillable_fields)) {
            $template->fillable_fields = self::FILLABLE_FIELDS;
            $template->save();
        }

        $contenidoVigente = $template->currentVersion->content ?? '';
        if (!str_contains($contenidoVigente, '{{doc.lugar_fecha}}')) {
            $contenidoNuevo = file_get_contents(self::RESOURCE_FILE);
            (new TemplateVersionService())->createVersion(
                $template,
                $contenidoNuevo,
                null,
                'Item #9990647: convierte a placeholders doc.* los 6 blancos que no son atributo del colaborador'
            );
        }
    }

    public function down(): void
    {
        // Versionado inmutable (no se borran versiones históricas); solo se limpia el catálogo.
        TalentoDocumentTemplate::where('name', self::NAME)->update(['fillable_fields' => null]);
    }
};
