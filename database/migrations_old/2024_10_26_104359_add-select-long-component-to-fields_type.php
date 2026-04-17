<?php

use App\Models\FieldModule;
use App\Models\FieldType;
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
        $ft = FieldType::create(['name' => 'select-long-component']);
        $module = Module::firstWhere('name', 'ClientAdditionalInformation');
        $fm = FieldModule::where('name', 'box_nomenclator')->where('module_id', $module->id)->first();
        $fm->type = $ft->id;
        $fm->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ft = FieldType::firstWhere('name', 'select-2-component');
        $module = Module::firstWhere('name', 'ClientAdditionalInformation');
        $fm = FieldModule::where('name', 'box_nomenclator')->where('module_id', $module->id)->first();
        $fm->type = $ft->id;
        $fm->save();
        $ft = FieldType::firstWhere('name', 'select-long-component');
        $ft->delete();
    }
};
