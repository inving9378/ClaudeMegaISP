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
        $module = Module::where('name', 'Task')->first();
        $module->columnsDatatable()->create([
            'name' => 'archived_at',
            'label' => 'Fecha Archivado',
            'order' => 13
        ]);
        $module->columnsDatatable()->create([
            'name' => 'finish_at',
            'label' => 'Fecha Terminado',
            'order' => 14
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->columnsDatatable()->where('name', 'archived_at')->delete();
        $module->columnsDatatable()->where('name', 'finish_at')->delete();
    }
};
