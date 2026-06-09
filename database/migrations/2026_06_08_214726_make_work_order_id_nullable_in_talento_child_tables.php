<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Capa 4.3 — work_order_id debe ser nullable en las tablas hijas para permitir
// filas que pertenecen a tasks (que usan tarea_id). Operación 100% aditiva:
// datos existentes y FKs se conservan; solo se relaja el NOT NULL constraint.
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
        'talento_route_stops',
        'talento_warranty_overrides',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                echo "⚠️  {$tableName} no existe — saltando" . PHP_EOL;
                continue;
            }

            // Buscar nombre real de FK que apunta a talento_work_orders
            $fkRow = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = 'work_order_id'
                   AND REFERENCED_TABLE_NAME = 'talento_work_orders'",
                [$tableName]
            );

            Schema::table($tableName, function (Blueprint $table) use ($fkRow) {
                if ($fkRow) {
                    $table->dropForeign($fkRow->CONSTRAINT_NAME);
                }
                $table->unsignedBigInteger('work_order_id')->nullable()->change();
            });

            // Re-crear FK con NULLABLE
            $newFk = $this->fkName($tableName);
            Schema::table($tableName, function (Blueprint $table) use ($newFk) {
                $table->foreign('work_order_id', $newFk)
                    ->references('id')->on('talento_work_orders')
                    ->onDelete('cascade');
            });

            echo "✅ {$tableName}.work_order_id → nullable, FK recreada" . PHP_EOL;
        }
    }

    public function down(): void
    {
        echo "⚠️  down() omitido: revertir a NOT NULL rompería filas con tarea_id." . PHP_EOL;
    }

    private function fkName(string $table): string
    {
        return 'fk_' . substr(
            str_replace(['talento_', 'work_order_'], ['t_', 'wo_'], $table),
            0, 30
        ) . '_wo';
    }
};
