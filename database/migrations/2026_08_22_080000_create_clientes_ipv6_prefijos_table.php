<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1044 (IPv6 1.2b, sub-item de #985/#811 sección 1.2) — asignación de
 * prefijo IPv6 delegado a un cliente/sesión PPPoE, ligado a la infraestructura
 * ya migrada en #1043 (ipv6_bloques/ipv6_routers/ipv6_plan_segmentos). Solo
 * modelo de datos, sin lógica de asignación real todavía. Path
 * database/migrations/ (no app/Modules/Addons/Ipv6/migrations/) — #984
 * (scaffold del ModuleServiceProvider) aún no está mergeado a main; mismo
 * patrón ya usado por los items hermanos #992/#1043/#1064/#1065. Columna
 * client_id (no cliente_id): la convención real del codebase para toda la
 * familia IPv6 (ver ipv6_policy_assignments.client_id) usa el nombre en
 * inglés pese a que el resto de columnas es español.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_ipv6_prefijos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('bloque_id')->constrained('ipv6_bloques')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('ipv6_routers')->cascadeOnDelete();
            $table->foreignId('segmento_id')->constrained('ipv6_plan_segmentos')->cascadeOnDelete();

            $table->string('usuario_pppoe', 150);
            $table->string('prefijo', 100);
            $table->string('duid', 150)->nullable();
            $table->timestamp('asignado_en');
            $table->timestamp('liberado_en')->nullable();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('estado', ['activo', 'liberado'])->default('activo');

            $table->timestamps();

            $table->index('prefijo');
            $table->index('usuario_pppoe');
            $table->index('client_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_ipv6_prefijos');
    }
};
