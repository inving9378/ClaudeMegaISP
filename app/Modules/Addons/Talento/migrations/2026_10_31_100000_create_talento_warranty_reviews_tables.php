<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** UNI-T L2: revisión de garantías (causa, responsabilidad, resolución) y catálogo de causas configurable (aditivo). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talento_warranty_causes')) {
            Schema::create('talento_warranty_causes', function (Blueprint $t) {
                $t->id();
                $t->string('clave', 40)->unique();
                $t->string('nombre', 120);
                $t->boolean('activa')->default(true);
                $t->timestamps();
            });
            $now = now();
            foreach ([['defecto_instalacion', 'Defecto de instalación'], ['equipo_defectuoso', 'Equipo defectuoso'], ['dano_cliente', 'Daño o manipulación del cliente'], ['causa_externa', 'Causa externa a la instalación'], ['sin_falla', 'Sin falla encontrada']] as [$c, $n]) {
                DB::table('talento_warranty_causes')->insert(['clave' => $c, 'nombre' => $n, 'activa' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! Schema::hasTable('talento_warranty_reviews')) {
            Schema::create('talento_warranty_reviews', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('warranty_event_id')->unique();
                $t->string('estado', 20)->default('abierta');          // abierta | en_revision | resuelta
                $t->unsignedBigInteger('revisor_user_id')->nullable();
                $t->unsignedBigInteger('causa_id')->nullable();
                $t->string('responsabilidad', 20)->nullable();         // ninguna | trabajo_original | externa | cliente
                $t->string('resolucion', 30)->nullable();              // sin_efecto | atribuida_trabajo_original | atribuida_externa
                $t->text('nota')->nullable();
                $t->timestamp('resuelta_at')->nullable();
                $t->timestamps();
                $t->index('estado');
            });
        }
    }

    public function down(): void
    {
        // Aditiva a propósito: conserva el historial de revisiones.
    }
};
