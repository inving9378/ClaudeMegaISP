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
        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->create([
            'name' => 'duration_contract_id',
            'label' => 'Duración del Contrato',
            'type' => 22,
            'additional_field' => false,
            'search' => json_encode([
                'model' => 'App\Models\DurationContract',
                'text' => 'name',
                'id' => 'id'
            ]),
            'position' => 32
        ]);
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->create([
            'name' => 'duration_contract_id',
            'label' => 'Duración del Contrato',
            'order' => 98,
        ]);
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->string('duration_contract_id')->nullable()->after('is_payment_activation_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->where('name', 'duration_contract_id')->first()->delete();
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'duration_contract_id')->first()->delete();
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropColumn('duration_contract_id');
        });

    }
};
