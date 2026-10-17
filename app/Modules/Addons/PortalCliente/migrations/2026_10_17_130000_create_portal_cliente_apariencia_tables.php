<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración → Portal Cliente: presentación administrable de las pantallas de acceso.
 *
 * - portal_cliente_apariencia_versiones: una fila por estado. Como mucho UN borrador y UNA
 *   publicada a la vez (lo garantizan los índices únicos sobre columnas que valen 1 o NULL;
 *   MySQL admite varios NULL). Las publicadas anteriores quedan como 'historial' y se pueden
 *   restaurar.
 * - portal_cliente_apariencia_imagenes: metadatos de las imágenes subidas. El archivo vive en el
 *   disco privado de Laravel (config portalcliente.apariencia.disco), nunca en la BD.
 *
 * Aditiva: solo crea tablas. down() las elimina (solo en rollback explícito).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_cliente_apariencia_versiones')) {
            Schema::create('portal_cliente_apariencia_versiones', function (Blueprint $table) {
                $table->id();
                $table->string('estado', 20)->index();              // borrador | publicada | historial
                $table->unsignedInteger('numero')->nullable();       // consecutivo de publicación
                $table->json('config');
                $table->unsignedInteger('revision')->default(1);     // candado optimista del borrador
                $table->unsignedBigInteger('base_version_id')->nullable();   // publicada sobre la que se editó
                $table->unsignedBigInteger('origen_version_id')->nullable(); // versión restaurada, si aplica
                $table->json('resumen_cambios')->nullable();
                $table->string('nota', 255)->nullable();
                $table->unsignedTinyInteger('borrador_unico')->nullable()->unique();
                $table->unsignedTinyInteger('publicada_unica')->nullable()->unique();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique('numero');
            });
        }

        if (! Schema::hasTable('portal_cliente_apariencia_imagenes')) {
            Schema::create('portal_cliente_apariencia_imagenes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('tipo', 20)->index();                // escritorio | movil | respaldo | logo
                $table->string('disco', 40);
                $table->string('ruta', 255);
                $table->string('mime', 60);
                $table->unsignedInteger('peso');
                $table->unsignedInteger('ancho');
                $table->unsignedInteger('alto');
                $table->char('sha256', 64);
                $table->string('nombre_original', 150)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_cliente_apariencia_imagenes');
        Schema::dropIfExists('portal_cliente_apariencia_versiones');
    }
};
