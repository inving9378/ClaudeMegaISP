<?php

use App\Models\CommandConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        CommandConfig::create([
            'command' => 'Actualizar Fechas Pasadas',
            'process_name' => 'update_task_old_command:process',
            'frequency_id' => 1,
            'execution_time' => '00:10',
            'command_description' => 'Actualiza la Fecha de las Tareas que ha pasado la fecha de ejecución',
            'status' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        CommandConfig::where('process_name', 'update_task_old_command:process')->first()->delete();
    }
};
