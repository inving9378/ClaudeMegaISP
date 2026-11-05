<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T J4 F1 (item #980): estado «pagada» de la liquidación + historial append-only de eventos.
 * Aditiva: nada la usa hasta encender unificacion.interruptores.liquidacion_estados_j4.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('talento_liquidations', 'paid_at')) {
            Schema::table('talento_liquidations', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('closed_at');
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
                $table->string('payment_reference', 120)->nullable()->after('paid_by');
            });
        }

        // Ampliar el enum conservando draft/closed y el default.
        DB::statement("ALTER TABLE talento_liquidations MODIFY status ENUM('draft','closed','paid') NOT NULL DEFAULT 'draft'");

        if (!Schema::hasTable('talento_liquidation_events')) {
            Schema::create('talento_liquidation_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('liquidation_id');
                $table->string('tipo', 40);
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('referencia', 120)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['liquidation_id', 'id'], 'tle_liq_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_liquidation_events');
    }
};
