<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1c del puente Vendedores→Talento (item #9990611, sub-item de #9990605).
 *
 * Tabla de auditoría del comando `talento:reconciliar-comisiones-espejo` (decisión Irving,
 * q3, opción 1: "log en archivo + registro en tabla de auditoría"). Solo persiste las
 * comparaciones que NO cuadran (estado <> OK) -- el detalle "sin diferencias" queda en el
 * canal de log de archivo (mismo patrón backup/pagos_recurrentes), no aquí.
 *
 * Append-only, sin updated_at, igual que talento_comisiones_espejo/talento_ledger_entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_reconciliacion_log', function (Blueprint $table) {
            $table->id();
            // Nullable: puede no resolverse un colaborador de Talento para el seller_id del
            // motor viejo (mismo caso "sin colaborador" que #9990606).
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->date('source_period_start')->nullable();
            $table->date('source_period_end')->nullable();
            $table->decimal('monto_vendedores', 12, 2)->default(0);
            $table->decimal('monto_espejo', 12, 2)->default(0);
            $table->decimal('diferencia', 12, 2)->default(0);
            $table->string('estado', 40);
            $table->timestamp('created_at')->nullable();

            $table->index(['colaborador_id', 'source_period_start']);
            $table->index('seller_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_reconciliacion_log');
    }
};
