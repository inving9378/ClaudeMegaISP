<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1311 (Recorridos, fase a) — fotos de un hallazgo. Se guardan reducidas (1600 px, calidad 80,
 * decisión 4 de #1315) en el disco privado; antes de reducirlas se leen del EXIF la ubicación y la
 * hora, que quedan aquí. `sha256` es de la foto tal como llegó (evita subir dos veces la misma).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_hallazgo_fotos')) {
            return;
        }

        Schema::create('talento_hallazgo_fotos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hallazgo_id');
            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->unsignedInteger('bytes_original')->nullable();
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->decimal('exif_lat', 10, 7)->nullable();
            $table->decimal('exif_lng', 10, 7)->nullable();
            $table->dateTime('exif_tomada_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('sha256', 'thfoto_sha_idx');
            $table->foreign('hallazgo_id', 'thfoto_hal_fk')
                ->references('id')->on('talento_hallazgos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_hallazgo_fotos');
    }
};
