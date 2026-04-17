<?php

use App\Http\Repository\FrequencyCommandRepository;
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
        $frequencyCommandRepository = new FrequencyCommandRepository();
        $frequencyId = $frequencyCommandRepository->getIdFilterByName('dailyAt');
        $input = [
            'command' => 'Slavar Base De Datos',
            'process_name' => 'backup_db:process',
            'frequency_id' => $frequencyId,
            'execution_time' => '00:05',
            'command_description' => 'Backup diaria de la base de datos',
            'status' => true,
        ];
        CommandConfig::create($input);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        CommandConfig::where('process_name','backup_db:process')->first()->delete();
    }
};
