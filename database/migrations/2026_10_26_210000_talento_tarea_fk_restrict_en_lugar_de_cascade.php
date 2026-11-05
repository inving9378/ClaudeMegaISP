<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T E3: las llaves tarea_id de Talento hacia tasks pasan de CASCADE a RESTRICT, para que borrar una
 * tarea nunca arrastre evidencias, firmas, incidencias, bono ni activaciones. Reversible (down vuelve a CASCADE).
 * Se descubren por information_schema; no se tocan los pivotes task_user / task_notifications.
 */
return new class extends Migration {
    private function llaves(string $regla): array
    {
        return DB::select(
            "select k.TABLE_NAME t, k.COLUMN_NAME c, k.CONSTRAINT_NAME n
             from information_schema.KEY_COLUMN_USAGE k
             join information_schema.REFERENTIAL_CONSTRAINTS r
               on r.CONSTRAINT_NAME = k.CONSTRAINT_NAME and r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA and r.TABLE_NAME = k.TABLE_NAME
             where k.TABLE_SCHEMA = database() and k.REFERENCED_TABLE_NAME = 'tasks'
               and k.TABLE_NAME like 'talento\\_%' and r.DELETE_RULE = ?",
            [$regla]
        );
    }

    private function cambiar(string $desde, string $hacia): void
    {
        if (!Schema::hasTable('tasks')) {
            return;
        }
        foreach ($this->llaves($desde) as $f) {
            DB::statement("ALTER TABLE `{$f->t}` DROP FOREIGN KEY `{$f->n}`");
            DB::statement("ALTER TABLE `{$f->t}` ADD CONSTRAINT `{$f->n}` FOREIGN KEY (`{$f->c}`) REFERENCES `tasks` (`id`) ON DELETE {$hacia}");
        }
    }

    public function up(): void
    {
        $this->cambiar('CASCADE', 'RESTRICT');
    }

    public function down(): void
    {
        $this->cambiar('RESTRICT', 'CASCADE');
    }
};
