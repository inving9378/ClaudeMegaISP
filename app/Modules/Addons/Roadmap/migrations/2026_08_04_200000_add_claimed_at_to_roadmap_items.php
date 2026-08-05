<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #507 sub-paso 3 — LEASE EXPLÍCITO del pool continuo. ADITIVA / reversible.
 *
 * `claimed_at` = cuándo una terminal RECLAMÓ el item, y se renueva con cada latido del worker
 * (`circuito:vivo --watch` → liveBeat). Hasta ahora el reaper deducía "worker muerto" solo de
 * `updated_at`, que se mueve con CUALQUIER escritura: un worker vivo que no escribía en el item
 * durante 25 min parecía muerto, y uno que escribía sin avanzar parecía vivo. Con el lease
 * explícito las dos señales son independientes y el reaper exige que AMBAS estén frías.
 *
 * Nullable a propósito: los items ya en vuelo al desplegar no lo traen y siguen evaluándose por
 * `updated_at` (mismo comportamiento de siempre) hasta que los reclame una terminal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('worker_sid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'claimed_at')) {
                $table->dropColumn('claimed_at');
            }
        });
    }
};
