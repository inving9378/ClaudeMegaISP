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
        $module = Module::where('name', 'Team')->first();
        $columnsDatatableByModule = [
            [
                'name' => 'users',
                'filter_name' => null,
                'label' => "Integrantes",
                'order' => 3
            ],

        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Team')->first();
        $module->columnsDatatable()->where('name', 'users')->delete();
    }
};
