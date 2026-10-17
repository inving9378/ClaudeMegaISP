<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versionado inmutable de plantillas (item #200 — Hijo B). Editar el contenido de una
 * plantilla NUNCA actualiza una version existente: siempre inserta una fila nueva y mueve el
 * puntero `current_version_id`. Asi un documento ya generado (Hijo D/E), que guarda el id de
 * la version con la que se genero, no cambia aunque la plantilla se edite despues.
 *
 * Item #9990806: createVersion() es tambien el UNICO punto donde se dispara la reapertura de
 * acuses (AcuseReopeningService) -- no duplicar esa logica en otro lado.
 */
class TemplateVersionService
{
    private static ?bool $conTipoCambio = null;

    public function __construct(private ?AcuseReopeningService $acuseReopeningService = null)
    {
        $this->acuseReopeningService ??= app(AcuseReopeningService::class);
    }

    public function createTemplate(array $attributes, string $content, ?int $userId = null, ?string $changeNote = null): TalentoDocumentTemplate
    {
        return DB::transaction(function () use ($attributes, $content, $userId, $changeNote) {
            $template = TalentoDocumentTemplate::create($attributes);
            // Version inicial: no hay acuses previos que reabrir (el template recien nace), por
            // eso 'menor' aqui aunque el default general del metodo sea 'mayor'.
            $this->createVersion($template, $content, $userId, $changeNote ?? 'Version inicial', 'menor');

            return $template->fresh('currentVersion');
        });
    }

    /**
     * Item roadmap #9990806 (sub-item de #9990792): $tipoCambio distingue version "mayor"
     * (cambio de contenido sustantivo) de "menor" (typo/formato). En una plantilla tipo='acuse',
     * una version "mayor" reabre (status -> 'pendiente') todo talento_employee_documents ya
     * 'completo' de ese template -- ver AcuseReopeningService. Default 'mayor' = comportamiento
     * seguro (reabre) para quien no especifique lo contrario, coincide con el comportamiento
     * base pedido por el item.
     */
    public function createVersion(TalentoDocumentTemplate $template, string $content, ?int $userId = null, ?string $changeNote = null, string $tipoCambio = 'mayor'): TalentoDocumentTemplateVersion
    {
        $version = DB::transaction(function () use ($template, $content, $userId, $changeNote, $tipoCambio) {
            $nextVersionNumber = (int) $template->versions()->max('version_number') + 1;

            $datos = [
                'template_id' => $template->id,
                'version_number' => $nextVersionNumber,
                'content' => $content,
                'change_note' => $changeNote,
                'created_by' => $userId,
                'created_at' => now(),
            ];
            // #531: la migración que siembra las plantillas (2026_09_02_150000) corre antes de la que
            // agrega `tipo_cambio` (2026_09_11_210000); en una BD nueva la columna aún no existe.
            if (self::$conTipoCambio ??= Schema::hasColumn('talento_document_template_versions', 'tipo_cambio')) {
                $datos['tipo_cambio'] = $tipoCambio;
            }

            $version = TalentoDocumentTemplateVersion::create($datos);

            $template->current_version_id = $version->id;
            $template->save();

            return $version;
        });

        if ($tipoCambio === 'mayor') {
            $this->acuseReopeningService->reopenForNewVersion($template->fresh(), $version);
        }

        return $version;
    }
}
