<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Capa 4.4 — tasks tipo=campo se auto-validan al completar (status=Done).
// validated_at se setea en OrdenTrabajoUnifiedService::completar().
// Tasks tipo='interna' nunca tienen validated_at (no afectan el ledger).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('validated_at')->nullable()->after('finish_at');
            // Índice compuesto para queries del LiquidationService
            $table->index(['tipo', 'validated_at', 'is_billable'], 'idx_tasks_liquidation');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_liquidation');
            $table->dropColumn('validated_at');
        });
    }
};
