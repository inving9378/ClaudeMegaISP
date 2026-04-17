<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->string('olt_power_dbm')->nullable()->after('power_dbm');
        });

        $module = Module::firstWhere(['name' => 'Client']);
        $module->columnsDatatable()->create([
            'name' => 'olt_power_dbm',
            'label' => 'Potencia OLT',
            'order' => 24
        ]);
        $module->columnsDatatable()->firstWhere('name', 'power_dbm')->update([
            'label' => 'Potencia Técnico',
        ]);

        $module = Module::firstWhere(['name' => 'ClientAdditionalInformation']);
        $module->fields()->create([
            'name' => 'olt_power_dbm',
            'label' => 'Potencia OLT',
            'placeholder' => '',
            'type' => 45,
            'position' => 5,
            'additional_field' => false,
        ]);

        try {
            Artisan::call('smartolt:sync-clients-with-ont');
        } catch (\Throwable) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->dropColumn('olt_power_dbm');
        });

        $module = Module::firstWhere(['name' => 'Client']);
        $module->columnsDatatable()->where('name', 'olt_power_dbm')->delete();
        $module->columnsDatatable()->firstWhere('name', 'power_dbm')->update([
            'label' => 'Potencia dBm',
        ]);

        $module = Module::firstWhere(['name' => 'ClientAdditionalInformation']);
        $module->fields()->where('name', 'olt_power_dbm')->delete();
    }
};
