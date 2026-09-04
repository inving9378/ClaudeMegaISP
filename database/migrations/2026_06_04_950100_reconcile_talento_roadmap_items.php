<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // talento_roadmap_items vive en el módulo Talento (app/Modules/Addons/Talento/migrations),
        // no en database/migrations: en la reconstrucción aislada de schema:build-reference esa
        // migración no corre, así que la tabla puede no existir todavía. En dev/prod reales sí existe.
        if (!Schema::hasTable('talento_roadmap_items')) {
            return;
        }

        // Ampliar enum para incluir 'pending'
        DB::statement("ALTER TABLE talento_roadmap_items MODIFY COLUMN status ENUM('backlog','pending','in_progress','done') NOT NULL DEFAULT 'backlog'");

        $canonical = [
            0 => ['title' => 'Auditoría e infraestructura base',       'status' => 'done'],
            1 => ['title' => 'Esqueleto e identidad del colaborador',   'status' => 'done'],
            2 => ['title' => 'Motor de compensación',                   'status' => 'done'],
            3 => ['title' => 'Asistencia y geocercas',                  'status' => 'done'],
            4 => ['title' => 'Planta interna (campo + cajas)',          'status' => 'done'],
            5 => ['title' => 'Planta externa (proyectos + calidad)',    'status' => 'done'],
            6 => ['title' => 'Control (penalizaciones + credenciales + fondos + préstamos + finiquito)', 'status' => 'done'],
            7 => ['title' => 'Academia y niveles',                     'status' => 'done'],
            8 => ['title' => 'Dashboards, escalafón y War Room',       'status' => 'in_progress'],
            9 => ['title' => 'Embajadores y resto del personal',       'status' => 'pending'],
        ];

        foreach ($canonical as $phase => $data) {
            DB::table('talento_roadmap_items')
                ->where('phase', $phase)
                ->update(['title' => $data['title'], 'status' => $data['status']]);
        }
    }

    public function down(): void {}
};
