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
        Schema::table('company_information', function (Blueprint $table) {
            $table->string('company_street')->nullable()->after('company_name');
            $table->string('company_external_number')->nullable()->after('company_street');
            $table->string('company_internal_number')->nullable()->after('company_external_number');
        });


        $module = Module::where('name', 'CompanyInformation')->first();

        $fields = [
            [
                'name' => 'company_street',
                'label' => 'Calle',
                'placeholder' => 'Calle',
                'type' => 1,
                'position' => 16,
                'additional_field' => false,
            ],
            [
                'name' => 'company_external_number',
                'label' => 'Numero Exterior',
                'placeholder' => 'Numero Exterior',
                'type' => 1,
                'position' => 17,
                'additional_field' => false,
            ],
            [
                'name' => 'company_internal_number',
                'label' => 'Numero Interior',
                'placeholder' => 'Numero Interior',
                'type' => 1,
                'position' => 18,
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
        Schema::table('company_information', function (Blueprint $table) {
            $table->dropColumn(['company_street', 'company_external_number', 'company_internal_number']);
        });

        $module = Module::where('name', 'CompanyInformation')->first();

        $module->fields()->where('name', 'company_street')->first()->delete();
        $module->fields()->where('name', 'company_external_number')->first()->delete();
        $module->fields()->where('name', 'company_internal_number')->first()->delete();
    }
};
