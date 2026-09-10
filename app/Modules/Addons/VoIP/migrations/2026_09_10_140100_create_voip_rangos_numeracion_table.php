<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de numeración — la estructura de la empresa, antes que las extensiones
 * (item #9990718 §7).
 *
 * `desde` y `hasta` son TEXTO, no enteros, a propósito: un plan puede empezar en
 * `0100` y guardarlo como número perdería el cero. Además obliga a comparar
 * longitudes explícitamente, que es una de las validaciones del item.
 *
 * `protegido` es el que evita el problema clásico: en 1900–1999 viven los enlaces
 * del sistema, y una extensión de persona ahí rompe la telefonía de la propia
 * instalación. El rechazo tiene que ser del modelo, no solo del formulario.
 *
 * SIN `tenant_id`, por decisión de arquitectura del item: este modelo vive en el
 * módulo VoIP, que está presente en todas las instalaciones y es donde de verdad
 * existen las extensiones. Voz Mayorista modela PLANTILLAS, no rangos por tenant.
 *
 * ADITIVA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voip_rangos_numeracion')) {
            return;
        }

        Schema::create('voip_rangos_numeracion', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->string('proposito', 160)->nullable();

            // Texto: respeta ceros a la izquierda.
            $table->string('desde', 20);
            $table->string('hasta', 20);

            $table->foreignId('voip_perfil_extension_id')
                ->nullable()
                ->constrained('voip_perfiles_extension')
                // restrict: un perfil en uso por un rango no se borra. Borrarlo
                // dejaría extensiones heredando de la nada.
                ->restrictOnDelete();

            $table->boolean('protegido')->default(false)
                ->comment('true = rechaza altas de extensiones de usuario (rango de sistema).');

            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->boolean('es_plantilla_sistema')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['desde', 'hasta']);
            $table->index('orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_rangos_numeracion');
    }
};
