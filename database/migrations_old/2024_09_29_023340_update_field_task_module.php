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
        $module->fields()->where('name', 'status')->first()->update([
            'options' => json_encode([
                'ToDo' => 'Por Hacer',
                'InProgress' => 'En Progreso',
                'Done' => 'Terminado',
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'status')->first()->update([
            'options' => json_encode([
                'ToDo' => 'Por Hacer',
                'InProgress' => 'En Progreso',
                'Done' => 'Terminado',
                'Archived' => 'Archivado'
            ])
        ]);
    }
};
