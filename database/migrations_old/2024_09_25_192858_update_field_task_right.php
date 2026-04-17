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
        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->where('name', 'assigned_to')->first()->update([
            'type' => 41,
        ]);

        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $fieldsLeft = [
            [
                'name' => 'observation',
                'label' => 'Observaciones',
                'placeholder' => 'Observaciones',
                'type' => 5,
                'position' => 14,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
        ];
        $moduleLeft->fields()->createMany($fieldsLeft);
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->where('name', 'assigned_to')->first()->update([
            'type' => 35,
        ]);

        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->fields()->where('name', 'observation')->first()->delete();
    }
};
