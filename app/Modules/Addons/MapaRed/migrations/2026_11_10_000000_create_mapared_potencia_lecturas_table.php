<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Item #1270 (MR-33) — serie de potencia por caja (NAP). Tabla aditiva. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mapared_potencia_lecturas')) {
            return;
        }
        Schema::create('mapared_potencia_lecturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('layer_id');
            $table->decimal('potencia_minima_dbm', 6, 2);
            $table->decimal('potencia_promedio_dbm', 6, 2)->nullable();
            $table->unsignedInteger('total_onus')->default(0);
            $table->timestamp('capturada_en')->useCurrent();

            $table->index(['layer_id', 'capturada_en'], 'mapared_potencia_layer_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_potencia_lecturas');
    }
};
