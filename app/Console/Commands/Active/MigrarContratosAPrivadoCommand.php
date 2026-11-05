<?php

namespace App\Console\Commands\Active;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * #599 (fase 2 de #570): mueve los contratos PDF ya generados del disco público
 * (storage/app/public/client/{id}/document/*.pdf) al disco local privado y reescribe files.path
 * a la ruta servida por controlador con permiso. Dry-run por DEFAULT; escribir exige --apply.
 *
 * En producción es una escritura manual: requiere entrada previa en docs/bitacora/ y la corre Irving.
 */
class MigrarContratosAPrivadoCommand extends Command
{
    protected $signature = 'contratos:migrar-a-privado {--apply : Ejecuta de verdad (sin esto solo reporta)} {--limit=0 : Máximo de archivos a procesar}';

    protected $description = 'Mueve contratos PDF existentes a disco privado y reescribe files.path (dry-run por default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && !$this->rutasServidorasExisten()) {
            $this->error('Abortado: las rutas /cliente/document/contrato/{id} y /crm/document/contrato/{id} no están registradas (falta #570 en este código). Reescribir files.path rompería los enlaces.');
            return self::FAILURE;
        }

        $files = File::where('fileable_type', 'App\Models\DocumentClient')
            ->where('path', 'like', '/storage/client/%/document/%')
            ->where('path', 'not like', '%/temp_contract_template/%')
            ->orderBy('id');
        if ((int) $this->option('limit') > 0) {
            $files->limit((int) $this->option('limit'));
        }

        $cuenta = ['movidos' => 0, 'faltan_en_disco' => 0, 'ambiguos' => 0, 'sin_documento' => 0];
        $this->info($apply ? 'MODO APPLY' : 'MODO DRY-RUN (no se escribe nada)');

        foreach ($files->get() as $file) {
            if (!preg_match('#^/storage/(client/(\d+)/document/.+\.pdf)$#i', $file->path, $m)) {
                continue;
            }
            [$origen, $x] = [$m[1], (int) $m[2]];

            $esCliente = DB::table('document_clients')->where('id', $file->fileable_id)->where('client_id', $x)->exists();
            $esCrm = DB::table('document_crms')->where('id', $file->fileable_id)->where('crm_id', $x)->exists();
            if ($esCliente && $esCrm) {
                $cuenta['ambiguos']++;
                $this->warn("AMBIGUO file#{$file->id} {$file->path}: coincide con cliente y con CRM; revisar a mano");
                continue;
            }
            if (!$esCliente && !$esCrm) {
                $cuenta['sin_documento']++;
                $this->warn("SIN DOCUMENTO file#{$file->id} {$file->path}");
                continue;
            }
            if (!Storage::disk('public')->exists($origen)) {
                $cuenta['faltan_en_disco']++;
                $this->warn("FALTA EN DISCO file#{$file->id} {$origen}");
                continue;
            }

            $tipo = $esCliente ? 'cliente' : 'crm';
            $destino = "contratos/{$tipo}/{$x}/document/{$file->fileable_id}-" . Str::slug(pathinfo($file->name, PATHINFO_FILENAME)) . '.pdf';
            $nuevoPath = "/{$tipo}/document/contrato/{$file->fileable_id}";
            $this->line("file#{$file->id} {$origen} -> local:{$destino} | path {$nuevoPath}");

            if ($apply) {
                Storage::disk('local')->put($destino, Storage::disk('public')->get($origen));
                if (Storage::disk('local')->size($destino) !== Storage::disk('public')->size($origen)) {
                    Storage::disk('local')->delete($destino);
                    $this->error("Tamaño distinto al copiar file#{$file->id}; se omite");
                    continue;
                }
                $file->path = $nuevoPath;
                $file->save();
                Storage::disk('public')->delete($origen); // solo tras copiar, verificar y reescribir
            }
            $cuenta['movidos']++;
        }

        $this->table(array_keys($cuenta), [array_values($cuenta)]);
        return self::SUCCESS;
    }

    private function rutasServidorasExisten(): bool
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map->uri();
        return $uris->contains('cliente/document/contrato/{documentId}') && $uris->contains('crm/document/contrato/{documentId}');
    }
}
