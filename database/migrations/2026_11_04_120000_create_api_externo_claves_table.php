<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1159 — claves para las consultas públicas (`/api/externo/*`). Se guarda solo la huella
 * sha256 de la clave: la clave completa se muestra una sola vez, al crearla. Tabla nueva, aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_externo_claves')) {
            return;
        }

        Schema::create('api_externo_claves', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('alcance', 50);
            $table->string('prefijo', 16);
            $table->char('huella', 64)->unique();
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->unsignedBigInteger('usos')->default(0);
            $table->timestamp('revocada_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['alcance', 'revocada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_externo_claves');
    }
};
