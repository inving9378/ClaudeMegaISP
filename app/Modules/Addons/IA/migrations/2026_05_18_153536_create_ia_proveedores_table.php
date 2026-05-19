<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('driver', ['claude', 'openai', 'gemini', 'openai_compatible', 'custom']);
            $table->text('api_key')->nullable();
            $table->string('endpoint_url')->nullable();
            $table->string('modelo_default');
            $table->boolean('soporta_imagenes')->default(false);
            $table->json('headers_personalizados')->nullable();
            $table->json('config_extra')->nullable();
            $table->boolean('activo')->default(true);
            $table->enum('estado', ['conectado', 'error', 'sin_configurar'])->default('sin_configurar');
            $table->text('ultimo_error')->nullable();
            $table->timestamp('probado_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_proveedores');
    }
};
