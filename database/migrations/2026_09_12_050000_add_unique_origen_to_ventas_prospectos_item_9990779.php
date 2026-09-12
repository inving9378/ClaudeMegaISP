<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #9990779 — catálogo único de prospectos.
 *
 * `ventas_prospectos` (#9990799) ya trae `origen`/`origen_id` para trazar la fuente legacy,
 * pero el índice era no-único. La consolidación (backfill + doble escritura vía observers de
 * CrmLeadInformation/CrmMainInformation) hace upsert por (`origen`,`origen_id`), así que un
 * único evita duplicar la fila del mismo prospecto legacy si el backfill se corre dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_prospectos', function (Blueprint $table) {
            $table->dropIndex(['origen', 'origen_id']);
            $table->unique(['origen', 'origen_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ventas_prospectos', function (Blueprint $table) {
            $table->dropUnique(['origen', 'origen_id']);
            $table->index(['origen', 'origen_id']);
        });
    }
};
