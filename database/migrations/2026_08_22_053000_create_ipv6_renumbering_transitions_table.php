<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1065 (IPv6 Fase 5.1b, sub-item de #1031) — log append-only de las
 * transiciones de estado de un plan de renumeración (#1064,
 * ipv6_renumbering_plans). FK propia hacia esa tabla (NO hacia #985/#986).
 *
 * Nunca se edita ni se borra una fila: solo `cuando` marca el momento (sin
 * updated_at, sin soft deletes). `quien_user_id` es nullable + `quien_nombre`
 * guarda el nombre en el momento de la transición, para no depender de que
 * el usuario siga existiendo (mismo criterio que otras tablas de auditoría
 * del proyecto, p.ej. ipv6_dual_stack_cutoff_dry_runs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_renumbering_transitions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('ipv6_renumbering_plans')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('quien_user_id')->nullable();
            $table->foreign('quien_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('quien_nombre', 255)->nullable();

            $table->timestamp('cuando')->useCurrent();

            $table->enum('de_estado', ['planificado', 'activo', 'en_deprecacion', 'retirado'])
                ->nullable();
            $table->enum('a_estado', ['planificado', 'activo', 'en_deprecacion', 'retirado']);

            $table->text('nota')->nullable();

            $table->index(['plan_id', 'cuando']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_renumbering_transitions');
    }
};
