<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1043 (IPv6 1.2a, sub-item de #985/#811 sección 1.2) — andamiaje
 * aditivo de infraestructura IPv6: bloques de direccionamiento delegados
 * por el proveedor de tránsito. Solo modelo de datos. Path
 * database/migrations/ — ver nota en 2026_08_22_070000_create_ipv6_routers_table.php.
 * Modelo ya mergeado: app/Modules/Addons/Ipv6/Models/Ipv6Bloque.php
 * (Model plano, sin created_by/updated_by).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_bloques', function (Blueprint $table) {
            $table->id();

            $table->string('prefijo', 100);
            $table->string('proveedor', 150)->nullable();
            $table->string('referencia_contrato', 150)->nullable();
            $table->string('ip_transito', 100)->nullable();
            $table->string('gateway_proveedor', 100)->nullable();
            $table->enum('estado', ['planificado', 'activo', 'en_deprecacion', 'retirado'])
                ->default('planificado');
            $table->timestamp('activado_en')->nullable();
            $table->timestamp('deprecado_en')->nullable();
            $table->timestamp('retirado_en')->nullable();

            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_bloques');
    }
};
