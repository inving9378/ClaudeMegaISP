<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\EmployeeDocumentPackageService;
use Illuminate\Console\Command;

/**
 * Item #9990645. `generateForColaborador` solo se dispara al ALTA del colaborador (observer
 * `created`) — un colaborador ya dado de alta que luego se le captura CURP/NSS/horario/etc, o
 * que se beneficia del fix de mapeo de este item (fecha.ciudad_firma/fecha.firma) o del cambio
 * de renderer ([FALTA:] -> linea en blanco), se queda con el HTML viejo hasta regenerarlo.
 * `generateForColaborador` hace upsert por [colaborador_id, template_id] — reprocesar es seguro
 * (no duplica filas, no borra firmas: `sign()`/`signature_path` viven en TalentoEmployeeDocument
 * y updateOrCreate no los toca porque no estan en el array de atributos que actualiza).
 */
class RegenerarDocumentosCommand extends Command
{
    protected $signature = 'talento:regenerar-documentos {colaborador_id? : ID de un colaborador puntual; si se omite, regenera todos los que tienen puesto}';
    protected $description = 'Re-renderiza los documentos del expediente RH con el mapeo/renderer actual (upsert, no destruye firmas).';

    public function handle(EmployeeDocumentPackageService $service): int
    {
        $colaboradorId = $this->argument('colaborador_id');

        $colaboradores = $colaboradorId
            ? TalentoColaborador::whereKey($colaboradorId)->get()
            : TalentoColaborador::whereNotNull('job_title')->get();

        if ($colaboradores->isEmpty()) {
            $this->warn('No hay colaboradores que regenerar.');

            return self::SUCCESS;
        }

        $totalDocumentos = 0;
        foreach ($colaboradores as $colaborador) {
            $documentos = $service->generateForColaborador($colaborador);
            $totalDocumentos += count($documentos);
            $this->line("Colaborador #{$colaborador->id}: " . count($documentos) . ' documento(s) regenerado(s).');
        }

        $this->info("Listo. {$totalDocumentos} documento(s) regenerado(s) en " . $colaboradores->count() . ' colaborador(es).');

        return self::SUCCESS;
    }
}
