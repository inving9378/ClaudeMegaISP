<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_caja_nivel2_simulaciones')) {
            return;
        }
        Schema::create('talento_caja_nivel2_simulaciones', function (Blueprint $table) {
            $table->id();
            $table->string('regla', 40)->default('caja_nivel2');
            $table->string('caja_ref', 60);
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->unsignedBigInteger('inspection_id')->nullable();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->decimal('power_dbm', 6, 2);
            $table->decimal('umbral_dbm', 6, 2);
            $table->boolean('estricto')->default(true);
            $table->decimal('monto_simulado', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['regla', 'caja_ref', 'periodo_inicio'], 'tcn2_regla_caja_periodo_uq');
            $table->index('colaborador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_caja_nivel2_simulaciones');
    }
};
