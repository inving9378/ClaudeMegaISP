<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-23 fase 4c (item roadmap #9990455) — fotos adjuntas a cualquier nodo o enlace del Mapa de
 * Red, vía relación polimórfica `fotoable` (MapaRedLayer/MapaRedDevice/MapaRedProyect/
 * MapaRedEnlaceServicio/MapaRedFiber). Diseño ya decidido por Irving (opción 1 de q2 del item
 * #9990455): tabla única polimórfica en vez de una tabla por tipo de elemento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_fotos', function (Blueprint $table) {
            $table->id();
            $table->morphs('fotoable');
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_fotos');
    }
};
