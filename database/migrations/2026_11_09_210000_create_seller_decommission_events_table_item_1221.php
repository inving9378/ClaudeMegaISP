<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1221 (#1216 F1b) — tabla aditiva de eventos de descomisión (solo bitácora).
 * No toca comisiones existentes ni saldos. La comisión se referencia de forma
 * genérica (tipo + id, sin FK) porque aún no hay política definida (F1a/F1c).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_decommission_events')) {
            return;
        }

        Schema::create('seller_decommission_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->string('comision_tipo', 100)->nullable();
            $table->unsignedBigInteger('comision_id')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->string('motivo', 500)->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->unsignedBigInteger('desbloqueado_por')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['comision_tipo', 'comision_id'], 'sde_comision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_decommission_events');
    }
};
