<?php

use App\Models\CommandConfig;
use App\Models\FrequencyCommand;
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
        FrequencyCommand::where('id', '>', 7)->delete();
        $frequency = [
            'name' => 'everyFourHours',
            'has_time' => 0
        ];

        $newFrequency = FrequencyCommand::create($frequency);

        $comand = [
            'command' => 'Rectificar el Address List',
            'process_name' => 'rectify_address_list:process',
            'frequency_id' => $newFrequency->id,
            'command_description' => 'Toma los Clientes Bloqueados con Servicios de Internet que no Tengan registro en el address list y los pone en el address list',
            'status' => 1
        ];

        CommandConfig::create($comand);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        CommandConfig::where('process_name', 'rectify_address_list:process')->delete();
        FrequencyCommand::where('name', 'everyFourHours')->delete();
    }
};
