<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_message_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ia_mensaje_id');
            $table->string('path');
            $table->string('nombre_original');
            $table->string('tipo_mime');
            $table->unsignedBigInteger('tamanio');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('ia_mensaje_id');

            $table->foreign('ia_mensaje_id')
                ->references('id')->on('ia_mensajes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_message_files');
    }
};
