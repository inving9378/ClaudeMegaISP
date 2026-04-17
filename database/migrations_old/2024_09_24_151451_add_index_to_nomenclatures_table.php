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
        Schema::table('nomenclatures', function (Blueprint $table) {
            $table->index('name');
        });

        $module = Module::where('name', 'ClientAdditionalInformation')->first();
        $module->fields()->where('name', 'box_nomenclator')->delete();

        $fields = [
            [
                'name' => 'box_nomenclator',
                'label' => 'Nomenclatura',
                'type' => 23,
                'placeholder' => 'Selecciona Nomenclatura',
                'position' => 7,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Nomenclature',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notUsed',
                ]),
            ]
        ];
        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomenclatures', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
        $module = Module::where('name', 'ClientAdditionalInformation')->first();
        $module->fields()->where('name', 'box_nomenclator')->delete();
    }
};
