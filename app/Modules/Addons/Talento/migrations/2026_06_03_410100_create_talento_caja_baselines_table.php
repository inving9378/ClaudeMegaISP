<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Baseline de potencia por caja/ODB — tabla propia porque no existe entidad de caja en el sistema
        Schema::create('talento_caja_baselines', function (Blueprint $table) {
            $table->id();
            $table->string('caja_ref', 60)->index(); // olt_onus.odb_name o identificador libre
            $table->unsignedBigInteger('olt_onu_id')->nullable(); // FK opcional si se conoce una ONU representativa
            $table->decimal('baseline_power_dbm', 5, 2);  // valor negativo típico, e.g. -21.50
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->timestamp('registered_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['caja_ref', 'registered_at'], 'tcb_caja_time_idx');
        });

        // Add caja_id to work orders (aditivo)
        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('caja_id')->nullable()->after('olt_onu_id');
        });

        // Health bonus settings
        if (Schema::hasTable('settings')) {
            $now = now();
            DB::table('settings')->updateOrInsert(
                ['key' => 'talento_health_bonus_amount'],
                ['value' => '30', 'description' => 'Monto del bono de salud de red por orden pagable (MXN)', 'updated_at' => $now]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'talento_health_bonus_max_loss_db'],
                ['value' => '1.0', 'description' => 'Pérdida máxima (dB) para calificar al bono de salud de red', 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->dropColumn('caja_id');
        });
        Schema::dropIfExists('talento_caja_baselines');
    }
};
