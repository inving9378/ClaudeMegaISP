<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #699 (Fase 1 de #678) — cimiento para detectar la reconexión
 * (offline→online) de un router Mikrotik vía polling. Solo trackea el último
 * estado conocido y cuándo cambió; NO dispara ningún sync (eso es Fase 2,
 * bloqueada por #676).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('routers', 'mikrotik_last_status')) {
            Schema::table('routers', function (Blueprint $table) {
                $table->string('mikrotik_last_status', 10)->nullable()->after('status');
                $table->timestamp('mikrotik_status_changed_at')->nullable()->after('mikrotik_last_status');
            });
        }
    }

    public function down(): void
    {
        // Sin drop a propósito (aditivo/reversible de bajo riesgo — guardrail #1018).
    }
};
