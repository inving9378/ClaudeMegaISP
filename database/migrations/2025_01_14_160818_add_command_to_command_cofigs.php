<?php

use App\Models\CommandConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        CommandConfig::create([
            'command' => 'Sincronizar Clientes entre Mikrotik y Sistema',
            'process_name' => 'app:rectify-clients-in-mikrotik',
            'frequency_id' => 7,
            'command_description' => 'Obtiene todos los ips del mikrotik
                            --> Obtiene todos los servicios de internet y de ellos filtra los clientes que estan activos y no activos
                            --> Compara los ips
                            --> Si el cliente esta activo y su ip esta en el address list de mikrotik, lo elimina del address list
                            --> Si el cliente NO esta activo y su ip NO esta en el address list de mikrotik, lo agrega al address list
                            --> Tambien recorre el PPOE y lo hace lo mismo, si el cliente no tiene registro en el PPOE lo agrega
    ',
            'status' => true
        ]);

        CommandConfig::create([
            'command' => 'Sincronizar Clientes entre Mikrotik y Sistema',
            'process_name' => 'mikrotikBackup:process',
            'frequency_id' => 1,
            'execution_time' => '23:00',
            'command_description' => 'Hace una copia de seguridad de Mikrotik diariamente',
            'status' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        CommandConfig::where('process_name', 'app:rectify-clients-in-mikrotik')->first()->delete();
        CommandConfig::where('process_name', 'mikrotikBackup:process')->first()->delete();
    }
};
