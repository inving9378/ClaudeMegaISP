<?php

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplateSignatureSlot;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9990646 (fase 1/2 de 4). Seed inicial de slots para las 11 plantillas
 * "personal" (items #201/#9990618). Se revisó el HTML real de cada una
 * (app/Modules/Addons/Talento/resources/document-templates-personal/*.html, bloque
 * `.bloque-firma`): las 11 traen EXACTAMENTE 2 firmantes — el bloque izquierdo siempre es quien
 * entrega/representa a la empresa (POR LA EMPRESA / ENTREGA / RESPONSABLE.../JEFE INMEDIATO /
 * RECURSOS HUMANOS) y el derecho siempre es la persona trabajadora/receptora (LA PERSONA
 * TRABAJADORA / TITULAR DE LOS DATOS / RECIBE / PERSONA RESPONSABLE DEL VEHÍCULO / RECIBÍ
 * DESCRIPCIÓN DE PUESTO). Se usan las mismas 2 keys ('empresa'/'trabajador') en las 11 para que
 * el render (fase 4) pueda usar placeholders uniformes `{{firma.empresa}}`/`{{firma.trabajador}}`;
 * el texto visible real de cada plantilla se conserva en `label`.
 * firmante_tipo: 'admin' = lo firma alguien de Meganet (RH/supervisor/entrega); 'colaborador' =
 * lo firma el propio empleado del expediente.
 * Idempotente (firstOrCreate por template_id+key) — si algún template no existe en este entorno
 * (no debería, ya seedeados por #201/#9990618/2026_09_08_180000), se omite sin abortar.
 */
return new class extends Migration
{
    private const SLOTS_POR_TEMPLATE = [
        'Contrato Individual de Trabajo' => [
            ['empresa', 'POR LA EMPRESA'],
            ['trabajador', 'LA PERSONA TRABAJADORA'],
        ],
        'Aviso de Privacidad para Personal' => [
            ['empresa', 'POR LA EMPRESA'],
            ['trabajador', 'TITULAR DE LOS DATOS'],
        ],
        'Reglamento Interior de Trabajo' => [
            ['empresa', 'POR LA EMPRESA'],
            ['trabajador', 'PERSONA TRABAJADORA'],
        ],
        'Entrega y Recepción de Herramientas y Equipo' => [
            ['empresa', 'ENTREGA'],
            ['trabajador', 'RECIBE'],
        ],
        'Carta Responsiva de Vehículo' => [
            ['empresa', 'ENTREGA / SUPERVISOR'],
            ['trabajador', 'PERSONA RESPONSABLE DEL VEHÍCULO'],
        ],
        'Responsiva de Activos Técnicos y de Comunicación' => [
            ['empresa', 'RESPONSABLE QUE ENTREGA'],
            ['trabajador', 'PERSONA QUE RECIBE'],
        ],
        'Acuse de Entrega de Equipo de Protección Personal (EPP)' => [
            ['empresa', 'ENTREGA Y EXPLICA'],
            ['trabajador', 'RECIBE Y SE COMPROMETE A UTILIZARLO'],
        ],
        'Carta de Conocimiento y Cumplimiento de Medidas de Seguridad' => [
            ['empresa', 'RESPONSABLE / INSTRUCTOR'],
            ['trabajador', 'PERSONA TRABAJADORA'],
        ],
        'Formato de Alta de Empleado' => [
            ['empresa', 'RECURSOS HUMANOS / ADMINISTRACIÓN'],
            ['trabajador', 'PERSONA TRABAJADORA'],
        ],
        'Descripción de Puesto - Técnico de Telecomunicaciones' => [
            ['empresa', 'JEFE INMEDIATO'],
            ['trabajador', 'RECIBÍ DESCRIPCIÓN DE PUESTO'],
        ],
        'Acuse de Políticas de Atención a Clientes, Efectivo y Trabajos No Autorizados' => [
            ['empresa', 'POR LA EMPRESA'],
            ['trabajador', 'PERSONA TRABAJADORA'],
        ],
    ];

    public function up(): void
    {
        foreach (self::SLOTS_POR_TEMPLATE as $templateName => $slots) {
            $template = TalentoDocumentTemplate::withTrashed()->where('name', $templateName)->first();
            if (!$template) {
                continue;
            }

            foreach ($slots as $orden => [$key, $label]) {
                TalentoDocumentTemplateSignatureSlot::firstOrCreate(
                    ['template_id' => $template->id, 'key' => $key],
                    [
                        'label' => $label,
                        'firmante_tipo' => $key === 'empresa' ? 'admin' : 'colaborador',
                        'orden' => $orden + 1,
                        'requerido' => true,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $templateIds = TalentoDocumentTemplate::withTrashed()
            ->whereIn('name', array_keys(self::SLOTS_POR_TEMPLATE))
            ->pluck('id');

        TalentoDocumentTemplateSignatureSlot::whereIn('template_id', $templateIds)
            ->whereIn('key', ['empresa', 'trabajador'])
            ->delete();
    }
};
