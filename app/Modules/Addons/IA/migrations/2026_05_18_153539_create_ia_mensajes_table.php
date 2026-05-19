<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ia_conversacion_id');
            $table->enum('rol', ['user', 'assistant', 'system']);
            $table->longText('contenido');
            $table->json('imagenes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('ia_conversacion_id');

            $table->foreign('ia_conversacion_id')
                ->references('id')->on('ia_conversaciones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_mensajes');
    }
};
