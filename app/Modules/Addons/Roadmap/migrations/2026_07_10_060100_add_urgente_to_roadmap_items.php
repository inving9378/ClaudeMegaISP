<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandera "Urgente" por item (#337): sube el item al frente de la cola de trabajo del
 * ejecutor (ordered() lo pone primero) y dispara una vuelta inmediata. Aditiva, con guard
 * hasColumn (idempotente/portable). NO toca el enum `priority` (se le sube a 'alta' aparte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'urgente')) {
                $table->boolean('urgente')->default(false)->index()->after('priority');
            }
            if (! Schema::hasColumn('roadmap_items', 'urgente_at')) {
                $table->timestamp('urgente_at')->nullable()->after('urgente');
            }
            if (! Schema::hasColumn('roadmap_items', 'urgente_by')) {
                $table->string('urgente_by')->nullable()->after('urgente_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (['urgente', 'urgente_at', 'urgente_by'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
