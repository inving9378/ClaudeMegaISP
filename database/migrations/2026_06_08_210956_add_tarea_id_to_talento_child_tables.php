<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Capa 4.1 — cada tabla hija de talento_work_orders recibe tarea_id nullable
// que apunta a tasks. Operación 100% aditiva: datos existentes y work_order_id
// no se tocan. talento_responsibility_windows usa source_work_order_id (no work_order_id).
return new class extends Migration
{
    private array $tables = [
        'talento_work_order_media',
        'talento_work_order_incidents',
        'talento_work_order_signatures',
        'talento_work_order_activations',
        'talento_work_order_activities',
        'talento_work_order_ia_validations',
        'talento_health_bonus_log',
        'talento_installation_surveys',
        'talento_responsibility_windows',
        'talento_route_stops',
        'talento_warranty_overrides',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                echo "⚠️  Tabla {$tableName} no existe — saltando" . PHP_EOL;
                continue;
            }

            if (Schema::hasColumn($tableName, 'tarea_id')) {
                echo "⚠️  {$tableName} ya tiene tarea_id — saltando" . PHP_EOL;
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $short = $this->shortName($tableName);
                $table->unsignedBigInteger('tarea_id')->nullable();
                $table->foreign('tarea_id', "fk_{$short}_tarea")
                      ->references('id')->on('tasks')
                      ->onDelete('cascade');
                $table->index('tarea_id', "idx_{$short}_tarea");
            });

            echo "✅ {$tableName} → tarea_id agregado" . PHP_EOL;
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName)) continue;
            if (! Schema::hasColumn($tableName, 'tarea_id')) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $short = $this->shortName($tableName);
                $table->dropForeign("fk_{$short}_tarea");
                $table->dropIndex("idx_{$short}_tarea");
                $table->dropColumn('tarea_id');
            });
        }
    }

    private function shortName(string $table): string
    {
        // Nombres cortos para FKs/índices (MySQL limita a 64 chars)
        return substr(
            str_replace(['talento_', 'work_order_'], ['t_', 'wo_'], $table),
            0,
            30
        );
    }
};
