<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-23 fase 4d (item roadmap #9990456) — historial de cambios por nodo/enlace del mapa.
 * Diseño decidido por Irving (q2 del propio item, Opción 1): tabla aditiva, un renglón por
 * campo de negocio cambiado (o un renglón sin `campo` para crear/eliminar), poblada por los
 * listeners de `MapaRedLayer` (nodo/enlace legacy, el modelo real que edita ElementSidePanel.vue)
 * registrados en `ModuleServiceProvider::boot()` — mismo patrón que ya usa `MapaRedEmpalme`
 * ahí mismo para invalidar caché. `entidad_tipo`/`entidad_id` quedan polimórficos a propósito
 * para poder sumar otros modelos MapaRed más adelante sin migración nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_historial', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_tipo');
            $table->unsignedBigInteger('entidad_id');
            $table->enum('accion', ['crear', 'editar', 'eliminar']);
            $table->string('campo')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entidad_tipo', 'entidad_id', 'created_at'], 'mapared_historial_entidad_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_historial');
    }
};
