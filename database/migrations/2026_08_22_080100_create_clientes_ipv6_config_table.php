<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1044 (IPv6 1.2b, sub-item de #985/#811 sección 1.2) — configuración
 * IPv6 por cliente (habilitación, compatibilidad de CPE, política de tráfico
 * entrante). Una fila por cliente (unique en client_id) — ver nota de
 * convención/path en 2026_08_22_080000_create_clientes_ipv6_prefijos_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_ipv6_config', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->boolean('ipv6_habilitado')->default(false);
            $table->enum('cpe_compatible', ['si', 'no', 'desconocido'])->default('desconocido');
            $table->string('cpe_modelo', 150)->nullable();
            $table->timestamp('cpe_verificado_en')->nullable();
            $table->enum('politica_entrante', ['bloqueado', 'abierto', 'personalizado'])->default('bloqueado');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_ipv6_config');
    }
};
