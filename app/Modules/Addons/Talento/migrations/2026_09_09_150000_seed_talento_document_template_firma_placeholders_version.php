<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Services\TemplateVersionService;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9990653 (fase 3a de #9990650). Los .html de
 * app/Modules/Addons/Talento/resources/document-templates-personal/ ya traen los
 * placeholders {{firma.empresa}}/{{firma.trabajador}}, pero el contenido real que sirve la
 * app vive en `talento_document_template_versions` (versionado inmutable, ver
 * TemplateVersionService: editar nunca actualiza una version existente, siempre crea una
 * nueva y mueve `current_version_id`). Esta migración crea esa version nueva para cada una
 * de las 11 plantillas leyendo el .html actualizado.
 * Idempotente: si `currentVersion->content` YA contiene '{{firma.' para ese template, no
 * crea otra version (evita duplicar versiones en reintentos/otros entornos que ya la tengan).
 */
return new class extends Migration
{
    private const RESOURCE_DIR = __DIR__.'/../resources/document-templates-personal/';

    /** nombre visible del template (igual a 2026_09_02_150000 / 2026_09_08_180000) => archivo fuente */
    private const TEMPLATES = [
        'Contrato Individual de Trabajo' => '01-contrato-individual-trabajo.html',
        'Aviso de Privacidad para Personal' => '02-aviso-privacidad-personal.html',
        'Reglamento Interior de Trabajo' => '03-reglamento-interior-trabajo.html',
        'Entrega y Recepción de Herramientas y Equipo' => '04-entrega-recepcion-herramientas-equipo.html',
        'Carta Responsiva de Vehículo' => '05-responsiva-vehiculo.html',
        'Responsiva de Activos Técnicos y de Comunicación' => '06-responsiva-activos-tecnicos-comunicacion.html',
        'Acuse de Entrega de Equipo de Protección Personal (EPP)' => '07-acuse-entrega-epp.html',
        'Carta de Conocimiento y Cumplimiento de Medidas de Seguridad' => '08-carta-medidas-seguridad.html',
        'Formato de Alta de Empleado' => '09-formato-alta-empleado.html',
        'Descripción de Puesto - Técnico de Telecomunicaciones' => '10-descripcion-puesto-tecnico-telecomunicaciones.html',
        'Acuse de Políticas de Atención a Clientes, Efectivo y Trabajos No Autorizados' => '11-acuse-atencion-clientes-efectivo-trabajos-no-autorizados.html',
    ];

    public function up(): void
    {
        $service = new TemplateVersionService();

        foreach (self::TEMPLATES as $name => $file) {
            $template = TalentoDocumentTemplate::withTrashed()->where('name', $name)->first();
            if (!$template) {
                continue;
            }

            $currentContent = $template->currentVersion?->content ?? '';
            if (str_contains($currentContent, '{{firma.')) {
                continue;
            }

            $content = file_get_contents(self::RESOURCE_DIR.$file);

            $service->createVersion($template, $content, null, 'Agrega placeholders de firma - item #9990650');
        }
    }

    public function down(): void
    {
        // Versionado inmutable a propósito (ver TemplateVersionService): no se borran versiones.
    }
};
