<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #992 (IPv6 Fase 3.2, sub-item de #954) — asignación de una fila de
 * ipv6_policies a un cliente. `estado` arranca en 'pendiente_piloto' (nunca
 * 'activa'): activarla de verdad implica tocar el CCR2216 real, y eso queda
 * para el sub-item de enforcement/piloto (requiere sesión con Irving, ver
 * comentarios de #992). Historial preservado vía estado/timestamps, sin
 * soft deletes ni update destructivo (mismo criterio que
 * ipv6_renumbering_transitions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_policy_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('ipv6_policy_id')->constrained('ipv6_policies');

            $table->enum('estado', ['pendiente_piloto', 'activa', 'revocada'])
                ->default('pendiente_piloto');
            $table->timestamp('activada_en')->nullable();
            $table->timestamp('revocada_en')->nullable();
            $table->text('notas')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_policy_assignments');
    }
};
