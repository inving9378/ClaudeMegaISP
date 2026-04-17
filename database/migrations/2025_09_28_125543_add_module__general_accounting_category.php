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
        $module = Module::create([
            'name' => 'GeneralAccountingCategory',
            'group' => 'Administration'
        ]);

        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'type' => 1,
                'position' => 1,
            ],
            [
                'name' => 'type_id',
                'label' => 'Balance',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 3,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\GeneralAccountingType',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
        ];

        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'GeneralAccountingCategory')->first();
        $module->fields()->delete();
        $module->delete();
    }
};
