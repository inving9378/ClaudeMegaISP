<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->unsignedBigInteger('ia_proyecto_id')->nullable();
            $table->unsignedBigInteger('ia_proveedor_id')->nullable();
            $table->string('modelo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('ia_proyecto_id');
            $table->index('ia_proveedor_id');

            $table->foreign('ia_proyecto_id')
                ->references('id')->on('ia_proyectos')
                ->nullOnDelete();

            $table->foreign('ia_proveedor_id')
                ->references('id')->on('ia_proveedores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_conversaciones');
    }
};
