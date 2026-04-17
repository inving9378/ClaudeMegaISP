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
            'name' => 'GeneralAccountingOperation',
            'group' => 'Administration'
        ]);

        $fields = [
            [
                'name' => 'general_accounting_category_id',
                'label' => 'Clasificacion',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\GeneralAccountingCategory',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notDefault'
                ]),
                'position' => 1,
            ],
            [
                'name' => 'description',
                'label' => 'Descripcion',
                'placeholder' => 'Descripcion',
                'type' => 5,
                'position' => 3,
                'additional_field' => false,
            ],
            [
                'name' => 'amount',
                'label' => 'Monto',
                'placeholder' => 'Monto',
                'type' => 15,
                'position' => 5,
                'additional_field' => false,
            ],
            [
                'name' => 'is_recurrent',
                'label' => 'Recurrente (OJO NO ESTA FUNCIONAL DE MOMENTO)',
                'placeholder' => 'Recurrente (OJO NO ESTA FUNCIONAL DE MOMENTO)',
                'type' => 16,
                'hint' => 'Recurrente (OJO NO ESTA FUNCIONAL DE MOMENTO)',
                'position' => 7,
                'additional_field' => false,
            ],
        ];

        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'GeneralAccountingOperation')->first();
        $module->fields()->delete();
        $module->delete();
    }
};
