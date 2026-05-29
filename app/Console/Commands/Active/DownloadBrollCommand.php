<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Marketing\Services\Video\BrollLibraryService;
use Illuminate\Console\Command;

class DownloadBrollCommand extends Command
{
    protected $signature = 'marketing:download-broll
                            {--company=1 : Company ID}
                            {--clips=5   : Clips per tag to download}';

    protected $description = 'Download initial B-roll library from Pexels for all active niches';

    public function handle(): int
    {
        $companyId    = (int) $this->option('company');
        $clipsPerTag  = (int) $this->option('clips');

        $this->info("Downloading B-roll for company {$companyId} ({$clipsPerTag} clips/tag)...");

        $svc    = new BrollLibraryService($companyId);
        $stats  = $svc->downloadInitialLibrary($clipsPerTag);

        if (!empty($stats['error'])) {
            $this->warn('Warning: ' . $stats['error']);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Downloaded', $stats['downloaded']],
                ['Skipped (already exists)', $stats['skipped']],
                ['Failed', $stats['failed']],
            ]
        );

        $this->info('Done. Storage: ' . storage_path('app/marketing/assets/broll'));

        return self::SUCCESS;
    }
}
