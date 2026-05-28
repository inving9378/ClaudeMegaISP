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
        $module = Module::firstWhere(['name' => 'Client']);
        $module->columnsDatatable()->where('name', 'id')->update([
            'order' => 1
        ]);
        $module->columnsDatatable()->create([
            'name' => 'status_smart',
            'label' => '',
            'order' => 0
        ]);

        $module = Module::firstWhere(['name' => 'ClientAdditionalInformation']);
        $module->fields()->where('name', 'olt_power_dbm')->delete();

        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->dropColumn('olt_power_dbm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::firstWhere(['name' => 'Client']);
        $module->columnsDatatable()->where('name', 'status_smart')->delete();
        $module->columnsDatatable()->where('name', 'id')->update([
            'order' => 0
        ]);

        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->string('olt_power_dbm')->nullable()->after('power_dbm');
        });
    }
};
