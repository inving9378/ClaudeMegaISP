<?php

namespace App\Modules\Addons\MegaFamilia\Console;

use App\Modules\Addons\MegaFamilia\Services\ApkDeployService;
use Illuminate\Console\Command;
use Throwable;

class DeployApkCommand extends Command
{
    protected $signature = 'megafamilia:deploy-apk
        {--source=            : Ruta al APK (default: build release de megafamilia-rn)}
        {--app-version=       : Forzar versionName, ej. 0.3.6}
        {--code=              : Forzar versionCode entero, ej. 9}
        {--changelog=         : Notas de la versión}
        {--mandatory          : Marcar como actualización obligatoria}
        {--force              : No pedir confirmación}';

    protected $description = 'Publica el APK de MegaFamilia: copia el archivo y actualiza app_versions en BD';

    public function __construct(private readonly ApkDeployService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->option('source') ?: ApkDeployService::DEFAULT_SOURCE_APK;

        // Muestra resumen antes de proceder
        $this->info('MegaFamilia · Deploy APK');
        $this->line("  Origen  : <comment>{$source}</comment>");
        $this->line("  Destino : <comment>" . public_path('downloads') . "/</comment>");

        if (!$this->option('force') && !$this->confirm('¿Continuar?', true)) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        $this->line('Desplegando...');

        try {
            $result = $this->service->deploy([
                'source'    => $this->option('source') ?: null,
                'version'   => $this->option('app-version') ?: null,
                'code'      => $this->option('code') ? (int) $this->option('code') : null,
                'changelog' => $this->option('changelog') ?: null,
                'mandatory' => $this->option('mandatory'),
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $v    = $result['version'];
        $mb   = number_format($result['bytes'] / 1024 / 1024, 2);

        $this->newLine();
        $this->info('✓ APK publicado correctamente');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Version',   "v{$v->version_name} (code {$v->version_code})"],
                ['Tamaño',    "{$mb} MB"],
                ['Archivo',   $v->file_path],
                ['Activa',    $v->active ? 'Sí' : 'No'],
                ['Obligatoria', $v->is_mandatory ? 'Sí' : 'No'],
                ['URL',       $result['url']],
            ]
        );

        return self::SUCCESS;
    }
}
