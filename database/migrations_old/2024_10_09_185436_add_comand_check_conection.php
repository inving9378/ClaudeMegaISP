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
            'command' => 'Chequear Conexion Con el Mikrotik',
            'process_name' => 'check_conection_mikrotik:process',
            'frequency_id' => 6,
            'execution_time' => null,
            'command_description' => 'Chequea la conexion con el mikrotik y envia notificacion a los administradores',
            'status' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       CommandConfig::where('process_name','check_conection_mikrotik:process')->delete();
    }
};
