<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #201 (Expediente RH — Hijo C). Convierte las plantillas .docx que Irving
 * entrego en /home/meganet/plantillas-rh/ a filas de `talento_document_templates` (motor del
 * Hijo B, #200) con category='personal'. Contenido HTML fuente en
 * app/Modules/Addons/Talento/resources/document-templates-personal/*.html, version inicial
 * creada via TemplateVersionService (versionado inmutable: corregir una plantilla despues es
 * una migracion nueva que llama createVersion(), nunca se edita esta).
 *
 * Casos especiales ya decididos por Irving (spec del item #201):
 * - La plantilla numero 03 NO existe en la entrega -> hallazgo registrado (ver
 *   comentarios_claude del item), no bloquea el resto de la conversion.
 * - La plantilla numero 10 se usa UNA sola vez; la entrega actual no trae un archivo
 *   duplicado "10_1" (verificado con ls antes de escribir esta migracion), asi que no hay
 *   nada que ignorar en este lote.
 *
 * Catalogo de variables (dot-notation) que consumen estas plantillas, documentado en
 * docs/talento-expediente-catalogo-variables.md para que el Hijo D (#202) arme el array de
 * datos con las mismas llaves al generar un documento real.
 */
return new class extends Migration
{
    private const RESOURCE_DIR = __DIR__.'/../resources/document-templates-personal/';

    /** archivo fuente => [nombre visible, descripcion corta] */
    private const TEMPLATES = [
        '01-contrato-individual-trabajo.html' => [
            'Contrato Individual de Trabajo',
            'Contrato laboral individual del expediente de personal.',
        ],
        '02-aviso-privacidad-personal.html' => [
            'Aviso de Privacidad para Personal',
            'Aviso de privacidad de datos personales para candidatos y trabajadores.',
        ],
        '04-entrega-recepcion-herramientas-equipo.html' => [
            'Entrega y Recepción de Herramientas y Equipo',
            'Responsiva de herramientas y equipo asignado, con tabla repetible de inventario.',
        ],
        '05-responsiva-vehiculo.html' => [
            'Carta Responsiva de Vehículo',
            'Responsiva del vehículo asignado a la persona trabajadora.',
        ],
        '06-responsiva-activos-tecnicos-comunicacion.html' => [
            'Responsiva de Activos Técnicos y de Comunicación',
            'Responsiva de activos técnicos y de comunicación, con tabla repetible.',
        ],
        '07-acuse-entrega-epp.html' => [
            'Acuse de Entrega de Equipo de Protección Personal (EPP)',
            'Acuse de EPP entregado, con tabla repetible.',
        ],
        '08-carta-medidas-seguridad.html' => [
            'Carta de Conocimiento y Cumplimiento de Medidas de Seguridad',
            'Carta de reglas de seguridad en campo.',
        ],
        '09-formato-alta-empleado.html' => [
            'Formato de Alta de Empleado',
            'Formato de alta con datos personales y laborales del expediente.',
        ],
        '10-descripcion-puesto-tecnico-telecomunicaciones.html' => [
            'Descripción de Puesto - Técnico de Telecomunicaciones',
            'Descripción de puesto y funciones del técnico de telecomunicaciones.',
        ],
        '11-acuse-atencion-clientes-efectivo-trabajos-no-autorizados.html' => [
            'Acuse de Políticas de Atención a Clientes, Efectivo y Trabajos No Autorizados',
            'Acuse de políticas de atención a clientes, manejo de efectivo y trabajos no autorizados.',
        ],
    ];

    public function up(): void
    {
        $service = new TemplateVersionService();

        foreach (self::TEMPLATES as $file => [$name, $description]) {
            if (TalentoDocumentTemplate::withTrashed()->where('name', $name)->exists()) {
                continue;
            }

            $content = file_get_contents(self::RESOURCE_DIR.$file);

            $service->createTemplate(
                ['name' => $name, 'category' => 'personal', 'description' => $description, 'active' => true],
                $content,
                null,
                'Conversión inicial desde .docx (item #201)'
            );
        }
    }

    public function down(): void
    {
        $names = array_map(fn ($t) => $t[0], self::TEMPLATES);
        TalentoDocumentTemplate::whereIn('name', $names)->forceDelete();
    }
};
