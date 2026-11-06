<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1044 (IPv6 1.2b, sub-item de #985/#811 sección 1.2) — bitácora de
 * despliegues de configuración IPv6 hacia un router (comandos generados +
 * resultado). Solo tracking, sin conexión real a RouterOS todavía. Ver nota
 * de convención/path en 2026_08_22_080000_create_clientes_ipv6_prefijos_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_despliegues', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bloque_id')->constrained('ipv6_bloques')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('ipv6_routers')->cascadeOnDelete();

            $table->string('tipo_operacion', 100);
            $table->text('comandos_generados');
            $table->enum('resultado', ['exito', 'parcial', 'fallo']);
            $table->text('respuesta_router')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ejecutado_en');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_despliegues');
    }
};
