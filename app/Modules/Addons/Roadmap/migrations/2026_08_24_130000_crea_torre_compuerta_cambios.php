<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablero de compuertas de la Torre — bitácora de cambios (regla 4 del entregable B).
 *
 * Toda acción disparada desde el tablero deja rastro: quién, cuándo, qué compuerta y
 * de qué valor a cuál. Es consultable desde el mismo tablero, así que la tabla vive
 * cerca del panel y no en un log de archivo que nadie abre.
 *
 * `valor_antes`/`valor_despues` son texto y no un tipo fuerte a propósito: las compuertas
 * miden cosas heterogéneas (un booleano de pausa, un nivel `A|B|C`, un id de item, el
 * número de slots libres) y el valor se guarda tal como se mostró en pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torre_compuerta_cambios', function (Blueprint $table) {
            $table->id();
            $table->string('compuerta', 60);
            $table->string('accion', 60);
            $table->text('valor_antes')->nullable();
            $table->text('valor_despues')->nullable();
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_login', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('compuerta');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torre_compuerta_cambios');
    }
};
