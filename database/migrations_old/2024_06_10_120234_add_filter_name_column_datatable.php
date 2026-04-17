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
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->where('name', 'internet_fees')->update([
            'filter_name' => 'networks.title'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->where('name', 'internet_fees')->update([
            'filter_name' => null
        ]);
    }
};
