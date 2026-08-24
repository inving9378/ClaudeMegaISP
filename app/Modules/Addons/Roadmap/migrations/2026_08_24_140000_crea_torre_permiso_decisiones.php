<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decisiones de Irving sobre los permisos del circuito.
 *
 * La tabla NO es la fuente de verdad de la autorización — esa sigue siendo Spatie.
 * Es el registro de QUIÉN decidió qué y CUÁNDO, incluyendo el caso importante: cuando
 * la decisión contradice la recomendación. Ese caso se marca explícitamente para que
 * más adelante nadie lo lea como un descuido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torre_permiso_decisiones', function (Blueprint $table) {
            $table->id();
            $table->string('permiso', 120);
            $table->string('rol', 60);
            $table->boolean('concedido');
            $table->string('recomendacion', 20);
            $table->boolean('contradice_recomendacion')->default(false);
            $table->text('nota')->nullable();
            $table->unsignedBigInteger('decidido_por')->nullable();
            $table->string('decidido_por_login', 100)->nullable();
            $table->timestamp('decidido_en')->nullable();
            $table->timestamps();

            $table->unique(['permiso', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torre_permiso_decisiones');
    }
};
