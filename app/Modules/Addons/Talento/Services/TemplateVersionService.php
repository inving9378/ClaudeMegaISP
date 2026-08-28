<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplateVersion;
use Illuminate\Support\Facades\DB;

/**
 * Versionado inmutable de plantillas (item #200 — Hijo B). Editar el contenido de una
 * plantilla NUNCA actualiza una version existente: siempre inserta una fila nueva y mueve el
 * puntero `current_version_id`. Asi un documento ya generado (Hijo D/E), que guarda el id de
 * la version con la que se genero, no cambia aunque la plantilla se edite despues.
 */
class TemplateVersionService
{
    public function createTemplate(array $attributes, string $content, ?int $userId = null, ?string $changeNote = null): TalentoDocumentTemplate
    {
        return DB::transaction(function () use ($attributes, $content, $userId, $changeNote) {
            $template = TalentoDocumentTemplate::create($attributes);
            $this->createVersion($template, $content, $userId, $changeNote ?? 'Version inicial');

            return $template->fresh('currentVersion');
        });
    }

    public function createVersion(TalentoDocumentTemplate $template, string $content, ?int $userId = null, ?string $changeNote = null): TalentoDocumentTemplateVersion
    {
        return DB::transaction(function () use ($template, $content, $userId, $changeNote) {
            $nextVersionNumber = (int) $template->versions()->max('version_number') + 1;

            $version = TalentoDocumentTemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => $nextVersionNumber,
                'content' => $content,
                'change_note' => $changeNote,
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $template->current_version_id = $version->id;
            $template->save();

            return $version;
        });
    }
}
