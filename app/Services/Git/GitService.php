<?php

namespace App\Services\Git;

use CzProject\GitPhp\GitRepository;
use Illuminate\Support\Facades\Log;

class GitService
{
    public function createGitTag(string $version, string $message = ''): bool
    {
        try {
            $repo = new GitRepository(base_path());
            $existingTags = $repo->getTags() ?? [];

            if (in_array($version, $existingTags)) {
                Log::warning("⚠️ El tag {$version} ya existe localmente.");
            } else {
                $repo->createTag($version);
                Log::info("✅ Tag {$version} creado correctamente.");
            }

            // Push es best-effort: si falla (ej. SSH no disponible en el proceso web)
            // no bloquea la creación de la release. El tag queda en local.
            try {
                $repo->run('push', 'origin', $version);
                Log::info("✅ Tag {$version} enviado al remoto.");
            } catch (\Throwable $e) {
                Log::warning("⚠️ Tag {$version} creado localmente pero no se pudo subir al remoto (push manual requerido): " . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("❌ Error al crear tag {$version}: " . $e->getMessage());
            return false;
        }
    }

    public function getTags(): array
    {
        $repo = new GitRepository(base_path());
        return $repo->getTags();
    }
}
