<?php

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
        Schema::table('bundles', function (Blueprint $table) {
            $table->boolean('cost_instalation_enable')->nullable()->default(false)->after('activation_fee');
            $table->string('cost_instalation')->nullable()->default(0)->after('cost_instalation_enable');
        });
        $module = \App\Models\Module::where('name','BundleRight')->first();

        $module->fields()->create([
            'name' =>  'cost_instalation_enable',
            'type' => 18,
            'position' => 0,
            'label' => 'Activar Costo de Instalacion',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "cost_instalation" => [
                    "field" => "cost_instalation",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Costo de Instalación",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" =>"full",
                    "options" => [
                        "min" => 0
                    ],
                ]
            ]),
        ]);

        $module->fields()->create([
           'name' =>  'cost_instalation',
            'type' => 30,
            'position' => 0,
            'label' => 'Costo de Instalación',
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('cost_instalation');
            $table->dropColumn('cost_instalation_enable');
        });
        $module = \App\Models\Module::where('name','BundleRight')->first();
        $module->fields()->where('name','cost_instalation_enable')->delete();
        $module->fields()->where('name','cost_instalation')->delete();
    }
};
