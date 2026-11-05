<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1145 — «Cómo debe funcionar» por módulo, con historial. Append-only: la versión vigente es la
 * última fila del módulo; nunca se edita ni se borra una versión anterior. Aditiva.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('roadmap_modulo_funcionamiento')) {
            return;
        }
        Schema::create('roadmap_modulo_funcionamiento', function (Blueprint $t) {
            $t->id();
            $t->string('modulo', 190)->index();
            $t->longText('texto');
            $t->unsignedBigInteger('autor_id')->nullable();
            $t->string('autor_nombre', 190)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_modulo_funcionamiento');
    }
};
