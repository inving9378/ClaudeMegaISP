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
        $module = Module::where('name', "Plantillas")->first();
        $module->fields()->create([
            'name' => 'data_template',
            'include' => true,
            'type' => 3, //hidden
            'position' => 999,
            'additional_field' => false

        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', "Plantillas")->first();
        $module->fields()->where('name', 'data_template')->delete();
    }
};
