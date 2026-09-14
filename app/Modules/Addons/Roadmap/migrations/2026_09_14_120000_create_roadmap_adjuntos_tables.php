<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9991163 (Fase 1 de #9991162) — Adjuntos del roadmap: maquetas, capturas, PDFs, evidencia que
 * una terminal debe tener a la mano al trabajar un item. Origen: #9990934 exigía una maqueta que
 * nunca existió en el repo y el Árbol se construyó a ciegas.
 *
 * Aditiva: dos tablas nuevas, ninguna existente se toca. Los bytes viven FUERA de public/
 * (disco `roadmap_adjuntos` → storage/app/roadmap/adjuntos, D1) y se sirven solo por controlador
 * con permiso Spatie, resueltos por id (nunca por ruta del cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roadmap_adjuntos')) {
            Schema::create('roadmap_adjuntos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_original', 255);        // D7: solo metadato, JAMÁS nombre en disco
                $table->string('ruta', 255);                    // relativa al disco: <uuid>.<ext>
                $table->string('extension', 10);
                $table->char('hash_sha256', 64)->unique();      // D6: dedupe — un archivo, un registro
                $table->string('mime', 100);                    // D4: MIME REAL (finfo), no el del navegador
                $table->unsignedBigInteger('tamano');           // bytes
                $table->string('descripcion', 255)->nullable(); // "maqueta de Panorama en árbol, contrato visual…"
                $table->unsignedInteger('veces_subido')->default(1); // D6: referencias de subida
                $table->unsignedBigInteger('subido_por')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();                          // borrado lógico (punto 5)
                $table->timestamp('purgar_despues_de')->nullable()->index(); // deleted_at + 30 días
                $table->timestamp('disco_purgado_at')->nullable();           // cuándo se quitó el archivo
            });
        }

        if (! Schema::hasTable('roadmap_adjunto_item')) {
            Schema::create('roadmap_adjunto_item', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('adjunto_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('amarrado_por')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['adjunto_id', 'item_id']);
                $table->index('item_id');
                $table->foreign('adjunto_id')->references('id')->on('roadmap_adjuntos')->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('roadmap_items')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_adjunto_item');
        Schema::dropIfExists('roadmap_adjuntos');
    }
};
