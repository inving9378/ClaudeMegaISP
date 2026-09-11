<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles de extensión — lo que una extensión hereda de su rango (item #9990718 §7).
 *
 * Existe para que crear la extensión 1201 no obligue a configurar códecs, permisos de
 * marcación y grabación a mano: hereda el perfil `tecnico_campo` de su rango y ya.
 *
 * Los dos defaults que importan son `permite_internacional` y `permite_premium`, ambos
 * en FALSE. Son los destinos donde el fraude telefónico cobra: una extensión
 * comprometida marcando a un número premium genera decenas de miles de pesos en una
 * madrugada. Habilitarlos tiene que ser un acto deliberado, nunca el estado por omisión.
 *
 * ADITIVA. No toca ninguna tabla existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voip_perfiles_extension')) {
            return;
        }

        Schema::create('voip_perfiles_extension', function (Blueprint $table) {
            $table->id();

            // Se resuelve por código, nunca por id: los ids de dev y prod divergen.
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();

            $table->boolean('permite_nacional_fijo')->default(true);
            $table->boolean('permite_nacional_movil')->default(true);
            // Los dos que el fraude usa. FALSE por omisión, siempre.
            $table->boolean('permite_internacional')->default(false);
            $table->boolean('permite_premium')->default(false);

            $table->boolean('permite_entrantes_exterior')->default(true);
            $table->boolean('graba_llamadas')->default(false);

            $table->json('codecs')->nullable();   // null = hereda el default del sistema

            // En centavos: nunca float para dinero. Null = sin límite propio.
            $table->bigInteger('limite_diario_centavos')->nullable();
            $table->bigInteger('limite_mensual_centavos')->nullable();
            $table->unsignedSmallInteger('canales_simultaneos_max')->nullable();

            // Espacio para reglas futuras sin migración.
            $table->json('politicas')->nullable();

            $table->boolean('es_plantilla_sistema')->default(false)
                ->comment('Lo sembró el seeder estándar: no se borra desde la UI.');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_perfiles_extension');
    }
};
