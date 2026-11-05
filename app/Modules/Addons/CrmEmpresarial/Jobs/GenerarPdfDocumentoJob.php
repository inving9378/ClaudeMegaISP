<?php

namespace App\Modules\Addons\CrmEmpresarial\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * DOCX -> PDF con LibreOffice headless (item #603). Un perfil temporal por trabajo
 * (-env:UserInstallation), timeout duro y un solo soffice a la vez (lock).
 * No convierte a imágenes: el PDF conserva texto.
 */
class GenerarPdfDocumentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(public string $docxRel) {}

    public function handle(): void
    {
        Cache::lock('crme-soffice', 140)->block(120, function () {
            $disk = Storage::disk('local');
            $in = $disk->path($this->docxRel);
            $outDir = dirname($in);
            $perfil = sys_get_temp_dir() . '/crme-lo-' . bin2hex(random_bytes(6));
            $p = new Process([
                'soffice', '--headless', '--norestore', '-env:UserInstallation=file://' . $perfil,
                '--convert-to', 'pdf', '--outdir', $outDir, $in,
            ], null, ['HOME' => $perfil], null, 120);
            try {
                $p->run();
            } finally {
                Process::fromShellCommandline('rm -rf ' . escapeshellarg($perfil))->run();
            }
            $pdf = preg_replace('/\.docx$/', '.pdf', $in);
            if (! $p->isSuccessful() || ! is_file($pdf) || filesize($pdf) === 0) {
                Log::error('crme: falló soffice', ['docx' => $this->docxRel, 'err' => $p->getErrorOutput()]);
                throw new \RuntimeException('No se pudo convertir el DOCX a PDF.');
            }
        });
    }
}
