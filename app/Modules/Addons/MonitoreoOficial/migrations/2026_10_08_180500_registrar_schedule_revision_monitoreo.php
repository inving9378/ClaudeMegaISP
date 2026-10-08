<?php

use Illuminate\Database\Migrations\Migration;
use App\Modules\Core\Configuracion\Models\CommandConfig;

/**
 * Registra el comando de revisión programada (Fase 4) en el schedule DINÁMICO
 * (tabla command_configs, que app/Console/Kernel.php ya lee en cada tick) — NO
 * se toca Kernel.php (archivo compartido de todo el sistema). firstOrCreate por
 * process_name: idempotente, y down() solo retira ESTA fila, nunca el mecanismo
 * de schedule dinámico en sí (que es del núcleo, no del módulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        CommandConfig::firstOrCreate(
            ['process_name' => 'monitoring:revisar-expedientes'],
            [
                'command' => 'Monitoreo Oficial — Revisión programada',
                'frequency_id' => 1, // dailyAt
                'execution_time' => '04:00',
                'command_description' => 'Encola una revisión (sella última comprobación / próxima revisión) de cada expediente activo de Monitoreo Oficial.',
                'status' => 1,
            ]
        );
    }

    public function down(): void
    {
        CommandConfig::where('process_name', 'monitoring:revisar-expedientes')->delete();
    }
};
