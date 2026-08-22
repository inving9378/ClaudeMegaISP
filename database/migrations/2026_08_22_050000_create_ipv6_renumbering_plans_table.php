<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1064 (IPv6 Fase 5.1a, sub-item de #1031) — tabla de planes de
 * renumeración IPv6 (RFC 4192). Migración propia y aditiva: SIN FK a las
 * tablas de #985/#986 porque aún no existen. `historico_preservado` es lo
 * que garantiza no perder el registro (en vez de soft deletes): un plan
 * liberado se marca (`liberado_en`), nunca se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_renumbering_plans', function (Blueprint $table) {
            $table->id();

            $table->string('bloque_viejo', 255); // CIDR libre, sin FK
            $table->string('bloque_nuevo', 255); // CIDR libre, sin FK

            $table->enum('estado', ['planificado', 'activo', 'en_deprecacion', 'retirado'])
                ->default('planificado');

            // Timers RFC4192 configurables por plan (no globales).
            $table->unsignedInteger('valid_lifetime_segundos')->nullable();
            $table->unsignedInteger('preferred_lifetime_segundos')->nullable();
            $table->timestamp('deprecacion_inicia_at')->nullable();
            $table->timestamp('retiro_programado_at')->nullable();

            $table->timestamp('morosos_reconstruido_at')->nullable();
            $table->boolean('historico_preservado')->default(true);
            $table->timestamp('liberado_en')->nullable(); // marca de "liberado", el registro NUNCA se borra
            $table->timestamp('simple_queues_actualizado_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_renumbering_plans');
    }
};
