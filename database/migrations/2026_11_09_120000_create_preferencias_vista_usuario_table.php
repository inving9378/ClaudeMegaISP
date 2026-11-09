<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de vista por usuario: lo que cada persona deja como predeterminado en un listado
 * (filtros, colores…). Una fila por usuario, vista y preferencia. Las vistas y preferencias
 * permitidas viven en config/preferencias_vista.php; la tabla no cambia al sumar otra lista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preferencias_vista_usuario')) {
            return;
        }

        Schema::create('preferencias_vista_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('vista', 60);
            $table->string('preferencia', 60);
            $table->json('valor');
            $table->timestamps();

            $table->unique(['user_id', 'vista', 'preferencia'], 'preferencias_vista_usuario_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_vista_usuario');
    }
};
