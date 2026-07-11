<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candado explícito "en desarrollo humano" (#341): un humano puede bloquear un item para que
 * el ejecutor autónomo NUNCA lo tome, aun si no está en_progreso. El guard del circuito salta
 * items con estado_aprobacion='en_progreso' O en_desarrollo_humano=true. Aditiva, idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'en_desarrollo_humano')) {
                $table->boolean('en_desarrollo_humano')->default(false)->index()->after('urgente_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'en_desarrollo_humano')) {
                $table->dropColumn('en_desarrollo_humano');
            }
        });
    }
};
