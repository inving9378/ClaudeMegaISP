<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #838 (Parte B del #795) — capítulos del Manual General de la Empresa.
 * Contenido editable vive en BD (nunca en Blade suelto); esta tabla es el
 * nivel superior del árbol capítulo → sección → versión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_manual_chapters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_manual_chapters');
    }
};
