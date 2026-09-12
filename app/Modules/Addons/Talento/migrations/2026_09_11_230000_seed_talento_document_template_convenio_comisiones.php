<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplateSignatureSlot;
use App\Modules\Addons\Talento\Models\TalentoPuesto;
use App\Modules\Addons\Talento\Models\TalentoPuestoDocumentTemplate;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9991021 (sub-item de seguimiento de #9990784). Andamiaje técnico: crea la
 * plantilla "Convenio Individual de Comisiones" en el motor de documentos de Talento, mismo
 * patrón que 2026_09_08_180000 (Reglamento Interior) + 2026_09_09_150100 (fillable_fields
 * doc.*): TalentoDocumentTemplate + TemplateVersionService::createTemplate +
 * TalentoDocumentTemplateSignatureSlot.
 *
 * El bloque de esquema/porcentaje/condiciones de comisión queda como placeholder {{doc.*}} SIN
 * valor real: docs/reglamento-ventas-comisiones.md no existe todavía (#9990775/#9990790 siguen
 * esperando el texto fuente de Irving) y talento_colaboradores no tiene ninguna columna de
 * esquema de comisión, así que no hay ni texto ni modelo de datos del que derivarlo. El HTML
 * deja el placeholder + una nota visible "(pendiente — ...)" para que no se confunda con un
 * campo listo para usarse.
 *
 * tipo='firma' (no el default 'acuse' de la columna, item #9990792): es un documento que
 * requiere aceptación firmada para tener efecto, mismo criterio que Contrato/Responsivas — el
 * propio backfill de esa migración ya incluye "convenio" en su lista de keywords de 'firma'.
 *
 * Asignación a puesto: la única entrada realmente comercial del catálogo `talento_puestos` es
 * "Vendedor" (ya con 6 plantillas asignadas) — se agrega ésta como obligatoria, mismo patrón que
 * sus hermanas. Si el catálogo no trajera ese puesto en algún entorno, se omite sin abortar (no
 * se inventa un mapeo).
 *
 * Idempotente: exists por nombre de plantilla; no pisa nada si ya existe.
 */
return new class extends Migration
{
    private const NAME = 'Convenio Individual de Comisiones';

    private const RESOURCE_FILE = __DIR__.'/../resources/document-templates-personal/12-convenio-individual-comisiones.html';

    private const DESCRIPTION = 'Convenio individual que regula el esquema de comisiones aplicable al colaborador, complementario al Contrato Individual de Trabajo.';

    private const FILLABLE_FIELDS = [
        ['key' => 'esquema_comision', 'label' => 'Esquema de comisión (pendiente definición de negocio, ver #9990790)', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'porcentaje_comision', 'label' => 'Porcentaje de comisión (pendiente definición de negocio, ver #9990790)', 'group' => 'doc', 'type' => 'text'],
        ['key' => 'condiciones_pago_comision', 'label' => 'Condiciones de pago de comisión (pendiente definición de negocio, ver #9990790)', 'group' => 'doc', 'type' => 'text'],
    ];

    public function up(): void
    {
        if (TalentoDocumentTemplate::withTrashed()->where('name', self::NAME)->exists()) {
            return;
        }

        $content = file_get_contents(self::RESOURCE_FILE);

        $template = (new TemplateVersionService())->createTemplate(
            [
                'name' => self::NAME,
                'category' => 'personal',
                'tipo' => 'firma',
                'description' => self::DESCRIPTION,
                'active' => true,
                'requires_signature' => true,
                'fillable_fields' => self::FILLABLE_FIELDS,
            ],
            $content,
            null,
            'Andamiaje técnico inicial (item #9991021) — placeholders de esquema/porcentaje/condiciones de comisión SIN valor real'
        );

        $slots = [
            ['key' => 'empresa', 'label' => 'POR LA EMPRESA', 'firmante_tipo' => 'admin', 'orden' => 1],
            ['key' => 'trabajador', 'label' => 'EL VENDEDOR', 'firmante_tipo' => 'colaborador', 'orden' => 2],
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

        $puestoComercial = TalentoPuesto::where('nombre', 'Vendedor')->first();
        if ($puestoComercial) {
            TalentoPuestoDocumentTemplate::firstOrCreate(
                ['puesto_id' => $puestoComercial->id, 'template_id' => $template->id],
                [
                    'puesto' => $puestoComercial->nombre,
                    'obligatorio' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        // Versionado inmutable a propósito (ver TemplateVersionService): esta migración es la
        // que CREA el template completo, así que su down() sí puede forceDelete (mismo patrón
        // que 2026_09_08_180000) — el cascade de FK limpia versiones/slots/asignación de puesto.
        TalentoDocumentTemplate::where('name', self::NAME)->forceDelete();
    }
};
