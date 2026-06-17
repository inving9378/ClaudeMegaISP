<?php

namespace App\Console\Commands\Active;

use App\Services\Updates\GitHubUpdateService;
use Illuminate\Console\Command;

class CheckGitHubUpdatesCommand extends Command
{
    protected $signature = 'updates:check-github';

    protected $description = 'Refresca el cache de detección de versión nueva desde GitHub (solo en consumidoras GITHUB_UPDATES_ENABLED=true).';

    public function handle(GitHubUpdateService $service): int
    {
        if (!config('updates.enabled')) {
            $this->line('GITHUB_UPDATES_ENABLED=false — comando ignorado.');
            return 0;
        }

        $this->info('Consultando GitHub Releases API…');
        $service->refresh();
        $result = $service->check();

        if ($result) {
            $this->info("Actualización disponible: {$result['tag']} (publicada {$result['published_at']})");
        } else {
            $this->info('Sin actualizaciones nuevas.');
        }

        return 0;
    }
}
