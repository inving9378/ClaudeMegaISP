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
        $module = Module::where('name', 'Release')->first();
        $module->fields()->where('name', 'sumary')->update(['name' => 'summary']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Release')->first();
        $module->fields()->where('name', 'summary')->update(['name' => 'sumary']);
    }
};
