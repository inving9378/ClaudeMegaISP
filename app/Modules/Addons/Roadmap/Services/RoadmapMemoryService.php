<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItemMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoadmapMemoryService
{
    const MEMORY_DIR = 'roadmap-memory';
    const DISK = 'local';
    const TEMPLATE_FILE = 'roadmap-memory/_template.md';

    public function fileName(int $itemId): string
    {
        return sprintf('item-%03d.md', $itemId);
    }

    public function filePath(int $itemId): string
    {
        return self::MEMORY_DIR . '/' . $this->fileName($itemId);
    }

    public function read(int $itemId): string
    {
        $path = $this->filePath($itemId);

        if (!Storage::disk(self::DISK)->exists($path)) {
            $this->createFromTemplate($itemId);
        }

        return Storage::disk(self::DISK)->get($path);
    }

    public function createFromTemplate(int $itemId): bool
    {
        $item = DB::table('roadmap_items')->where('id', $itemId)->first();
        if (!$item) {
            return false;
        }

        $template = $this->getTemplate();
        $content = str_replace(
            ['{{ID}}', '{{TITLE}}', '{{STATUS}}', '{{PRIORITY}}', '{{VERSION}}', '{{CREATED_AT}}', '{{DESCRIPTION}}'],
            [
                str_pad($itemId, 3, '0', STR_PAD_LEFT),
                $item->title ?? '(sin título)',
                $item->status ?? 'pending',
                $item->priority ?? 'media',
                $item->target_version ?? '(sin versión)',
                $item->created_at ?? now()->toDateString(),
                $item->description ?? '(sin descripción)',
            ],
            $template
        );

        Storage::disk(self::DISK)->put($this->filePath($itemId), $content);
        $this->syncIndex($itemId, $content);

        return true;
    }

    public function appendToSection(int $itemId, string $section, string $content): bool
    {
        $existing = $this->read($itemId);
        $sessionNum = $this->getNextSessionNumber($itemId);
        $timestamp = now()->format('Y-m-d H:i');

        $newEntry = "\n### {$timestamp} — Sesión {$sessionNum}\n{$content}\n";

        if (strpos($existing, "## {$section}") !== false) {
            // Insertar al final de la sección, antes de la siguiente ## o al final del archivo
            $pattern = '/(## ' . preg_quote($section, '/') . '[\s\S]*?)(\n## |\z)/';
            if (preg_match($pattern, $existing)) {
                $existing = preg_replace($pattern, '$1' . $newEntry . '$2', $existing, 1);
            } else {
                $existing .= $newEntry;
            }
        } else {
            $existing .= "\n\n## {$section}\n{$newEntry}";
        }

        Storage::disk(self::DISK)->put($this->filePath($itemId), $existing);
        $this->syncIndex($itemId, $existing);

        return true;
    }

    public function syncIndex(int $itemId, string $content): void
    {
        $hash    = hash('sha256', $content);
        $size    = strlen($content);
        $summary = $this->extractLastSessionSummary($content);

        $existing = RoadmapItemMemory::where('roadmap_item_id', $itemId)->first();

        if ($existing) {
            $existing->update([
                'file_path'            => $this->filePath($itemId),
                'file_size_bytes'      => $size,
                'content_hash'         => $hash,
                'session_count'        => $existing->session_count + 1,
                'summary_last_session' => $summary,
                'last_appended_at'     => now(),
            ]);
        } else {
            RoadmapItemMemory::create([
                'roadmap_item_id'      => $itemId,
                'file_path'            => $this->filePath($itemId),
                'file_size_bytes'      => $size,
                'content_hash'         => $hash,
                'session_count'        => 1,
                'summary_last_session' => $summary,
                'last_appended_at'     => now(),
            ]);
        }
    }

    public function generateContinuationPrompt(int $itemId): string
    {
        $content = $this->read($itemId);
        $item    = DB::table('roadmap_items')->where('id', $itemId)->first();

        $idPad = str_pad($itemId, 3, '0', STR_PAD_LEFT);

        return <<<PROMPT
# Prompt para Claude Code — Continuación item #{$itemId}

> Generado automáticamente desde /releases. Lee el contexto completo abajo antes de actuar.

═══ INICIO PROMPT ═══

# Tarea: Continuar trabajo en item #{$itemId} del roadmap

## 🎯 Contexto del item

**Título**: {$item->title}
**Status actual**: {$item->status}
**Prioridad**: {$item->priority}
**Versión objetivo**: {$item->target_version}

## 📜 Memoria completa acumulada (CLAUDE.md del item)

A continuación está TODO el historial de este item, incluyendo todas las sesiones previas, decisiones tomadas, hallazgos y la siguiente acción concreta:

```markdown
{$content}
```

## ⚠️ Reglas críticas

1. **Lee la sección "Siguiente acción concreta"** del CLAUDE.md arriba — ES tu primera tarea
2. **NO repitas trabajo** que aparezca como done en sub-tareas
3. **NO contradigas decisiones** registradas en la bitácora
4. **Mantén actualizado el CLAUDE.md** al terminar (ver paso final)
5. Si surge una sub-tarea o decisión nueva, registrarla en su sección correspondiente

## 📝 PASO FINAL OBLIGATORIO — Actualizar CLAUDE.md

Al terminar tu trabajo, actualiza el archivo usando el servicio:

```php
\$svc = app(\App\Modules\Addons\Roadmap\Services\RoadmapMemoryService::class);
\$svc->appendToSection({$itemId}, 'Bitácora cronológica', "### [TIMESTAMP] — Sesión [N+1]\\n**Qué se hizo**:\\n- ...\\n\\n**Hallazgos**:\\n- ...\\n\\n**Pendiente**:\\n- ...");
```

O directamente al archivo `storage/app/roadmap-memory/item-{$idPad}.md`.

═══ FIN PROMPT ═══
PROMPT;
    }

    protected function getNextSessionNumber(int $itemId): int
    {
        $memory = RoadmapItemMemory::where('roadmap_item_id', $itemId)->first();
        return $memory ? $memory->session_count + 1 : 1;
    }

    protected function extractLastSessionSummary(string $content): string
    {
        if (preg_match_all('/### \d{4}-\d{2}-\d{2}[\s\S]*?(?=### \d{4}-\d{2}-\d{2}|\z)/', $content, $matches)) {
            $lastSession = end($matches[0]);
            return mb_substr(strip_tags($lastSession), 0, 200);
        }
        return mb_substr($content, 0, 200);
    }

    protected function getTemplate(): string
    {
        if (Storage::disk(self::DISK)->exists(self::TEMPLATE_FILE)) {
            return Storage::disk(self::DISK)->get(self::TEMPLATE_FILE);
        }

        // Fallback inline si el archivo no existe
        return $this->inlineTemplate();
    }

    protected function inlineTemplate(): string
    {
        return <<<'TPL'
# Item ID {{ID}} — {{TITLE}}

## Metadata
- **Status**: {{STATUS}}
- **Priority**: {{PRIORITY}}
- **Target Version**: {{VERSION}}
- **Creado**: {{CREATED_AT}}
- **Última sesión**: (aún sin sesiones)

## Descripción original
{{DESCRIPTION}}

## Estado actual
(Se actualizará cuando se trabaje en este item por primera vez.)

## Siguiente acción concreta
(Definir al iniciar la primera sesión.)

## Sub-tareas
(Las sub-tareas se irán llenando conforme avance el trabajo.)

## Bitácora cronológica
(Vacía. Cada sesión agregará una entrada aquí.)

## Contexto técnico
(Paths, archivos, comandos relevantes — se llenan conforme se descubran.)

## Archivos relevantes
(Lista de archivos del proyecto que tocan este item.)
TPL;
    }
}
