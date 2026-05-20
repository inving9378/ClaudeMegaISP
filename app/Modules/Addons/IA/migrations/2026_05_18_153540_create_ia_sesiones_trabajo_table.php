<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_sesiones_trabajo', function (Blueprint $table) {
            $table->id();
            $table->text('resumen')->nullable();
            $table->json('archivos_modificados')->nullable();
            $table->json('prompts_destacados')->nullable();
            $table->string('proveedor_ia_usado')->nullable();
            $table->timestamp('inicio_sesion');
            $table->timestamp('fin_sesion')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('inicio_sesion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_sesiones_trabajo');
    }
};
