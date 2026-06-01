<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->json('subtasks')->nullable()->after('prompt');
            $table->json('log')->nullable()->after('subtasks');
        });

        // Inicializar filas existentes con array vacío
        DB::table('roadmap_items')
            ->whereNull('subtasks')
            ->update(['subtasks' => '[]', 'log' => '[]']);

        // Sembrar las 5 sub-tareas por defecto en el item in_progress existente.
        // Todas en false — el admin las marcará desde la UI según avance real.
        $defaultSubtasks = json_encode([
            ['title' => 'Prompt enviado a Claude Code.',                              'completed' => false, 'completed_at' => null],
            ['title' => 'Reporte/plan recibido y aprobado.',                          'completed' => false, 'completed_at' => null],
            ['title' => 'Implementación reportada por Claude Code.',                  'completed' => false, 'completed_at' => null],
            ['title' => 'Verificación server-side (Claude Code).',                    'completed' => false, 'completed_at' => null],
            ['title' => 'Verificación visual (admin, navegador, claro y oscuro).',    'completed' => false, 'completed_at' => null],
        ]);

        DB::table('roadmap_items')
            ->where('status', 'in_progress')
            ->update(['subtasks' => $defaultSubtasks]);
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropColumn(['subtasks', 'log']);
        });
    }
};
