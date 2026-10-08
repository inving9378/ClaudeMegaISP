<?php

namespace App\Modules\Addons\MonitoreoOficial\Services;

use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCase;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringSource;
use Illuminate\Support\Facades\Log;

/**
 * Siembra IDEMPOTENTE del expediente de ejemplo de MEGANET (IMPI 2806655).
 *
 * ⚠️ Este seeder NO se invoca desde ninguna migración ni seeder universal del
 *    módulo: es dato de MEGANET, no dato inicial de todos los ISP. Solo lo llaman
 *    (a) ModuleDefinition::install() cuando la instancia es MEGANET (operador), y
 *    (b) el comando `monitoring:import-demo-impi` (importación expresa).
 */
class ImpiDemoSeeder
{
    /** @return array{created:bool, case_id:int|null} */
    public function seed(string $origen = 'manual'): array
    {
        $source = MonitoringSource::firstOrCreate(
            ['authority' => 'IMPI', 'source_type' => 'manual_pdf'],
            [
                'name'          => 'IMPI — Carga manual de documentos',
                'adapter_class' => 'App\\Modules\\Addons\\MonitoreoOficial\\Sources\\ManualPdfAdapter',
                'status'        => 'disponible',
                'configuration' => ['nota' => 'Importación manual de PDF oficial; sin scraping del portal.'],
            ]
        );

        // Idempotente por (authority, case_number): si ya existe, no duplica.
        $case = MonitoringCase::firstOrNew([
            'authority'   => 'IMPI',
            'case_number' => '2806655',
        ]);

        $created = ! $case->exists;

        // No sobreescribir si alguien ya editó el expediente real; solo rellenar
        // en el alta inicial. El estado jamás se marca como "aprobado/resuelto".
        if ($created) {
            $case->fill([
                'source_id'            => $source->id,
                'name'                 => 'MEGANETMX TE CONECTA',
                'registration_number'  => '2592552',
                'reference_number'     => '708517',
                'class_code'           => '38',
                'current_legal_status' => 'Declaración incorporada al expediente, sin resolución favorable confirmada',
                'monitoring_status'    => 'activo',
                'is_demo'              => true,
                'metadata'             => [
                    'servicios'            => 'Servicios de telecomunicaciones',
                    'fecha_concesion'      => '2023-08-24',
                    'vencimiento_registro' => '2033-08-24',
                    'declaracion_presentada' => '2026-09-15',
                    'documento'            => 'MA/E/2026/0387400',
                    'codigo_vidoc'         => '7939938',
                    'correo_alertas'       => 'inving9378@hotmail.com',
                    'origen_siembra'       => $origen,
                    // Candado semántico: el comprobante de presentación NO es resolución.
                    'advertencia'          => 'La declaración presentada NO equivale a resolución favorable. No generar alerta de aprobación desde el comprobante.',
                ],
            ])->save();

            Log::info("[MonitoreoOficial] Expediente demo IMPI 2806655 sembrado (origen={$origen}).");
        }

        return ['created' => $created, 'case_id' => $case->id];
    }
}
