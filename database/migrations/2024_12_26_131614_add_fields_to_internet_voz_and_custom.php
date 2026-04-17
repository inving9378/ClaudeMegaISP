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
        $module = Module::where('name','Internet')->first();
        $module->fields()->create([
            'name' =>  'promotion_enable',
            'type' => 18,
            'position' => 25,
            'label' => 'Activar Promoción',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "discount_period" => [
                    "field" => "discount_period",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Período de Descuento",
                    "placeholder" => "Seleccione un periodo de descuento",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        '1' => '1 mes',
                        '2' => '2 meses',
                        '3' => '3 meses',
                        '4' => '4 meses',
                        '5' => '5 meses',
                        '6' => '6 meses',
                        '7' => '7 meses',
                        '8' => '8 meses',
                        '9' => '9 meses',
                        '10' => '10 meses',
                        '11' => '11 meses',
                        '12' => '12 meses',
                        '16' => '1 año y 4 meses',
                        '18' => '1 año y 6 meses',
                        '24' => '2 años',
                    ],
                ],
                "discount_value_fixed" => [
                    "field" => "discount_value_fixed",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Tasa fija de Descuento",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        "min" => 0
                    ],
                ],
                "discount_value" => [
                    "field" => "discount_value",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Porciento de Descuento",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        "min" => 0
                    ],
                ],
            ]),
        ]);


        $module->fields()->create([
            'name' => 'discount_value',
            'type' => 30,
        ]);

        $module->fields()->create([
            'name' => 'discount_value_fixed',
            'type' => 30,
        ]);

        $module->fields()->create([
            'name' => 'discount_period',
            'type' => 30,
        ]);


        Schema::table('internets', function (Blueprint $table) {
            $table->string('promotion_enable')->nullable()->after('cost_instalation_enable')->nullable();
            $table->string('init_date_discount')->nullable()->after('promotion_enable')->nullable();
            $table->string('end_date_discount')->nullable()->after('init_date_discount')->nullable();
            $table->string('discount_value_fixed')->nullable()->after('end_date_discount')->nullable();
            $table->string('discount_value')->nullable()->after('discount_value_fixed')->nullable();
            $table->string('discount_period')->nullable()->after('discount_value')->nullable();
        });


        //Voz
        $module = Module::where('name','Voise')->first();
        $module->fields()->create([
            'name' =>  'promotion_enable',
            'type' => 18,
            'position' => 26,
            'label' => 'Activar Promoción',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "discount_period" => [
                    "field" => "discount_period",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Período de Descuento",
                    "placeholder" => "Seleccione un periodo de descuento",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        '1' => '1 mes',
                        '2' => '2 meses',
                        '3' => '3 meses',
                        '4' => '4 meses',
                        '5' => '5 meses',
                        '6' => '6 meses',
                        '7' => '7 meses',
                        '8' => '8 meses',
                        '9' => '9 meses',
                        '10' => '10 meses',
                        '11' => '11 meses',
                        '12' => '12 meses',
                        '16' => '1 año y 4 meses',
                        '18' => '1 año y 6 meses',
                        '24' => '2 años',
                    ],
                ],
                "discount_value_fixed" => [
                    "field" => "discount_value_fixed",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Tasa fija de Descuento",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        "min" => 0
                    ],
                ],
                "discount_value" => [
                    "field" => "discount_value",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Porciento de Descuento",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        "min" => 0
                    ],
                ],
            ]),
        ]);


        $module->fields()->create([
            'name' => 'discount_value',
            'type' => 30,
        ]);

        $module->fields()->create([
            'name' => 'discount_value_fixed',
            'type' => 30,
        ]);

        $module->fields()->create([
            'name' => 'discount_period',
            'type' => 30,
        ]);

        Schema::table('voises', function (Blueprint $table) {
            $table->string('promotion_enable')->nullable()->after('cost_instalation_enable')->nullable();
            $table->string('init_date_discount')->nullable()->after('promotion_enable')->nullable();
            $table->string('end_date_discount')->nullable()->after('init_date_discount')->nullable();
            $table->string('discount_value_fixed')->nullable()->after('end_date_discount')->nullable();
            $table->string('discount_value')->nullable()->after('discount_value_fixed')->nullable();
            $table->string('discount_period')->nullable()->after('discount_value')->nullable();
        });

        //custom
        $module = Module::where('name','Custom')->first();
        $module->fields()->create([
            'name' =>  'promotion_enable',
            'type' => 18,
            'position' => 26,
            'label' => 'Activar Promoción',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "discount_period" => [
                    "field" => "discount_period",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Período de Descuento",
                    "placeholder" => "Seleccione un periodo de descuento",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        '1' => '1 mes',
                        '2' => '2 meses',
                        '3' => '3 meses',
                        '4' => '4 meses',
                        '5' => '5 meses',
                        '6' => '6 meses',
                        '7' => '7 meses',
                        '8' => '8 meses',
                        '9' => '9 meses',
                        '10' => '10 meses',
                        '11' => '11 meses',
                        '12' => '12 meses',
                        '16' => '1 año y 4 meses',
                        '18' => '1 año y 6 meses',
                        '24' => '2 años',
                    ],
                ],
                "discount_value_fixed" => [
                    "field" => "discount_value_fixed",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Tasa fija de Descuento",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        "min" => 0
                    ],
                ],
                "discount_value" => [
                    "field" => "discount_value",
                    "type" => "input-number",
                    "value" => null,
                    "label" => "Porciento de Descuento",
                    "placeholder" => "0.00",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "options" => [
                        "min" => 0
                    ],
                ],
            ]),
        ]);


        $module->fields()->create([
            'name' => 'discount_value',
            'type' => 30,
        ]);

        $module->fields()->create([
            'name' => 'discount_value_fixed',
            'type' => 30,
        ]);

        $module->fields()->create([
            'name' => 'discount_period',
            'type' => 30,
        ]);

        Schema::table('customs', function (Blueprint $table) {
            $table->string('promotion_enable')->nullable()->after('mac_enable')->nullable();
            $table->string('init_date_discount')->nullable()->after('promotion_enable')->nullable();
            $table->string('end_date_discount')->nullable()->after('init_date_discount')->nullable();
            $table->string('discount_value_fixed')->nullable()->after('end_date_discount')->nullable();
            $table->string('discount_value')->nullable()->after('discount_value_fixed')->nullable();
            $table->string('discount_period')->nullable()->after('discount_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internets', function (Blueprint $table) {
            $table->dropColumn('promotion_enable');
            $table->dropColumn('init_date_discount');
            $table->dropColumn('end_date_discount');
            $table->dropColumn('discount_value_fixed');
            $table->dropColumn('discount_value');
            $table->dropColumn('discount_period');
        });
        $module = Module::where('name','Internet')->first();
        $module->fields()->where('name', 'promotion_enable')->delete();
        $module->fields()->where('name', 'init_date_discount')->delete();
        $module->fields()->where('name', 'end_date_discount')->delete();
        $module->fields()->where('name', 'discount_value_fixed')->delete();

        Schema::table('voises', function (Blueprint $table) {
            $table->dropColumn('promotion_enable');
            $table->dropColumn('init_date_discount');
            $table->dropColumn('end_date_discount');
            $table->dropColumn('discount_value_fixed');
            $table->dropColumn('discount_value');
            $table->dropColumn('discount_period');
        });
        $module = Module::where('name','Voise')->first();
        $module->fields()->where('name', 'promotion_enable')->delete();
        $module->fields()->where('name', 'init_date_discount')->delete();
        $module->fields()->where('name', 'end_date_discount')->delete();
        $module->fields()->where('name', 'discount_value_fixed')->delete();

        Schema::table('customs', function (Blueprint $table) {
            $table->dropColumn('promotion_enable');
            $table->dropColumn('init_date_discount');
            $table->dropColumn('end_date_discount');
            $table->dropColumn('discount_value_fixed');
            $table->dropColumn('discount_value');
            $table->dropColumn('discount_period');
        });
        $module = Module::where('name','Custom')->first();
        $module->fields()->where('name', 'promotion_enable')->delete();
        $module->fields()->where('name', 'init_date_discount')->delete();
        $module->fields()->where('name', 'end_date_discount')->delete();
        $module->fields()->where('name', 'discount_value_fixed')->delete();
    }
};
