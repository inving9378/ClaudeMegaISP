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
        $module = Module::where('name', 'TemplateTask')->first();
        $module->fields()->where('name', 'assigned_to')->update([
            'type' => 41,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'TemplateTask')->first();
        $module->fields()->where('name', 'assigned_to')->update([
            'type' => 35,
        ]);
    }
};
