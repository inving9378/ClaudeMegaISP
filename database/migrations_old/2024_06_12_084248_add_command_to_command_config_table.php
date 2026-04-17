<?php

use App\Http\Repository\FrequencyCommandRepository;
use App\Models\CommandConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        $inputs = [
            [
                'name' => 'everyFiveMinutes',
                'has_time' => 0,
            ],
            [
                'name' => 'everyTenMinutes',
                'has_time' => 0,
            ],
            [
                'name' => 'everyFifteenMinutes',
                'has_time' => 0,
            ],
            [
                'name' => 'everyThirtyMinutes',
                'has_time' => 0,
            ],
            [
                'name' => 'hourly',
                'has_time' => 0,
            ],
        ];

        foreach ($inputs as $value) {
            DB::table('frequency_commands')->insert($value);
        }

        $frequencyCommandRepositroy = new FrequencyCommandRepository();
        $frequencyId = $frequencyCommandRepositroy->getIdFilterByName('everyFiveMinutes');

        $newCommand = [
            'command' => 'Recificar la Tabla Address List',
            'process_name' => 'removeservicefromaddresslist-deployed-charged:process',
            'frequency_id' => $frequencyId,
            'execution_time' => '',
            'command_description' => 'Recorre la Tabla de address list y los servicios que han sido pagados y desplegados que estan activos los saca del address list',
            'status' => '1',
        ];

        CommandConfig::create($newCommand);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
