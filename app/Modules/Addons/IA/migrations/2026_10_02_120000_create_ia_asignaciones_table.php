<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ia_asignaciones')) {
            return;
        }

        Schema::create('ia_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();          // 'global' o 'modulo.funcion'
            $table->unsignedBigInteger('ia_proveedor_id')->nullable();
            $table->string('modelo', 100)->nullable();       // override del modelo_default del proveedor
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('ia_proveedor_id')->references('id')->on('ia_proveedores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_asignaciones');
    }
};
