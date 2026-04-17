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
            // Verificamos si el tag ya existe
            $existingTags = $repo->getTags();
            if ($existingTags && in_array($version, $existingTags)) {
                Log::warning("⚠️ El tag {$version} ya existe, se omitió su creación.");
                return false;
            }

            // Crear el tag
            $repo->createTag($version);
            Log::info("✅ Tag {$version} creado correctamente.");

            // Subir el tag al remoto
            $repo->run('push', 'origin', $version);
            Log::info("✅ Tag {$version} enviado correctamente al remoto.");

            return true;
        } catch (\Throwable $e) {
            Log::error("❌ Error al crear o subir tag {$version}: " . $e->getMessage());
            return false;
        }
    }

    public function getTags(): array
    {
        $repo = new GitRepository(base_path());
        return $repo->getTags();
    }
}
