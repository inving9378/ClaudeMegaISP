<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #992 (IPv6 Fase 3.2, sub-item de #954) — andamiaje aditivo de la
 * política de tráfico entrante IPv6 por cliente (q3 aprobada por Irving).
 * Solo modelo de datos: NO toca MikroTik/CCR2216 ni activa ningún
 * enforcement real (eso requiere #953 cerrado + medición de connection
 * tracking del piloto, decisión de Irving — ver sub-items de #992).
 * `modo_entrante` arranca en 'deny_default' porque la descripción del item
 * exige bloqueado-por-defecto con excepciones explícitas antes del drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_policies', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();

            $table->enum('modo_entrante', ['deny_default', 'allow_default'])
                ->default('deny_default');

            $table->unsignedInteger('rate_in_mbps')->nullable();
            $table->unsignedInteger('rate_out_mbps')->nullable();
            $table->string('acl_profile', 150)->nullable();
            $table->json('excepciones')->nullable();

            $table->enum('estado', ['borrador', 'activa', 'retirada'])->default('borrador');
            $table->timestamp('retirada_en')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_policies');
    }
};
