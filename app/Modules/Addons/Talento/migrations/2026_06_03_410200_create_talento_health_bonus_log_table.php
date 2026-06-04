<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log del cotejo de potencia por orden (registro independiente del ledger)
        Schema::create('talento_health_bonus_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('colaborador_id');
            $table->string('caja_ref', 60)->nullable();
            $table->decimal('baseline_power_dbm', 5, 2)->nullable();
            $table->decimal('client_power_dbm', 5, 2)->nullable();
            $table->decimal('loss_db', 5, 2)->nullable();       // = baseline − client
            $table->decimal('max_loss_db', 4, 2);               // threshold at time of check
            $table->boolean('bonus_awarded')->default(false);
            $table->decimal('bonus_amount', 8, 2)->default(0);
            $table->string('power_source', 40)->nullable();     // 'olt_onu', 'client_additional', 'manual'
            $table->string('skip_reason', 120)->nullable();     // why bonus was skipped
            $table->timestamp('checked_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
            $table->index(['colaborador_id', 'checked_at'], 'thbl_col_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_health_bonus_log');
    }
};
