<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1047 (IPv6 Fase 3.1a, sub-item de #991) — tabla de auditoría del
 * orquestador dry-run del corte dual-stack (IPv4+IPv6) por cliente.
 *
 * Registra la INTENCIÓN de un corte (los comandos que se generarían para
 * MgNet_Morosos + MgNet_Morosos_V6), nunca una acción real: el servicio que
 * escribe aquí (Ipv6DualStackCutoffOrchestrator) NUNCA abre conexión al
 * router (ver MikrotikIpv6Client.php:14-20, mismo alcance duro). Deja lista
 * la pieza de software para cuando #953 (Fase 2, delegación IPv6 a clientes)
 * aporte el prefijo real por cliente — hoy `ipv6_prefix` puede ser sintético.
 *
 * Modelo plano SIN LogsActivity a propósito (mismo criterio que
 * `auditoria_senales`/FleetPosition): es en sí misma una tabla de auditoría,
 * envolverla en BaseModel solo duplicaría cada fila dentro de `activity_log`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_dual_stack_cutoff_dry_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('ipv4', 45);
            $table->string('ipv6_prefix', 64);
            $table->string('router_version', 32);
            $table->string('driver', 96);
            $table->string('comentario', 255)->nullable();
            $table->json('comandos');
            $table->json('advertencias')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_dual_stack_cutoff_dry_runs');
    }
};
