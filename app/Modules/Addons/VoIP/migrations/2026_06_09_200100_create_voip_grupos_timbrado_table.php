<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voip_grupos_timbrado', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->enum('estrategia', ['ringall', 'hunt', 'memoryhunt'])->default('ringall');
            $table->unsignedSmallInteger('ring_time')->default(20);
            $table->enum('destino_fallback', ['buzon', 'colgar', 'repetir'])->default('colgar');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('voip_grupo_extension', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('extension_id');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->foreign('grupo_id')->references('id')->on('voip_grupos_timbrado')->cascadeOnDelete();
            $table->foreign('extension_id')->references('id')->on('voip_extensiones')->cascadeOnDelete();
            $table->unique(['grupo_id', 'extension_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_grupo_extension');
        Schema::dropIfExists('voip_grupos_timbrado');
    }
};
