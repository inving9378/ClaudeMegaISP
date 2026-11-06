<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1044 (IPv6 1.2b, sub-item de #985/#811 sección 1.2) — excepciones a
 * la política de tráfico entrante por cliente (reglas puntuales de firewall
 * IPv6). Ver nota de convención/path en
 * 2026_08_22_080000_create_clientes_ipv6_prefijos_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_ipv6_excepciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('descripcion', 255);
            $table->enum('protocolo', ['tcp', 'udp', 'icmpv6', 'any']);
            $table->string('puertos', 100)->nullable();
            $table->string('direccion_destino', 100)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();

            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_ipv6_excepciones');
    }
};
