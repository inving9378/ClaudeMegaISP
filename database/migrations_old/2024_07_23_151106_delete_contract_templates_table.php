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
        $module = Module::where('name', 'Plantillas')->first();
        $module->fields()->delete();
        $module->packages()->delete();
        $module->delete();
        Schema::dropIfExists('contract_templates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
