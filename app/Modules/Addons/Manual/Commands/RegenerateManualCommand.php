<?php

namespace App\Modules\Addons\Manual\Commands;

use App\Modules\Addons\Manual\Services\ManualGeneratorService;
use Illuminate\Console\Command;

class RegenerateManualCommand extends Command
{
    protected $signature = 'manual:regenerate {--section= : Slug específico a regenerar}';

    protected $description = 'Regenera el manual de usuario vía Claude API';

    public function handle(ManualGeneratorService $service): int
    {
        $section = $this->option('section');

        if ($section) {
            $this->info("Regenerando manual — sección: {$section}");
        } else {
            $this->info('Regenerando manual completo...');
        }

        try {
            $service->onProgress(function (string $msg) {
                $this->line('  ' . $msg);
            });
            $result = $service->generate($section ?: null);
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $generated = $result['generated'] ?? 0;
        $errors    = $result['errors'] ?? [];

        if ($section) {
            if ($generated === 0) {
                $this->warn("Sin coincidencias para slug '{$section}'. Nada que regenerar.");
            } else {
                $this->info("✓ Sección {$section} generada");
            }
        } else {
            $this->info("✓ Manual completo regenerado ({$generated} secciones)");
        }

        if (!empty($errors)) {
            $this->warn(count($errors) . ' errores durante la generación:');
            foreach ($errors as $err) {
                $this->line('  - ' . $err);
            }
        }

        return self::SUCCESS;
    }
}
