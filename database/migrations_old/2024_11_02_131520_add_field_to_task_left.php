<?php

use App\Models\Module;
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
        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $fieldsLeft = [
            [
                'name' => 'geo_data',
                'label' => 'Datos Geograficos',
                'placeholder' => '19.700586990172585,-99.07096803188318',
                'type' => 38,
                'position' => 14,
                'additional_field' => false,
            ],
        ];
        $moduleLeft->fields()->createMany($fieldsLeft);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleLeft = Module::where('name', 'TaskLeft')->first();

        $moduleLeft->fields()->where('name', 'geo_data')->delete();
    }
};
