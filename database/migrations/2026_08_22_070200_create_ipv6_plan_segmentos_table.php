<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1043 (IPv6 1.2a, sub-item de #985/#811 sección 1.2) — andamiaje
 * aditivo de infraestructura IPv6: segmentación del plan de direccionamiento
 * (qué porción de un bloque va a cada router/uso). Solo modelo de datos.
 * Path database/migrations/ — ver nota en 2026_08_22_070000_create_ipv6_routers_table.php.
 * Modelo ya mergeado: app/Modules/Addons/Ipv6/Models/Ipv6PlanSegmento.php
 * (Model plano, sin created_by/updated_by). FKs mínimas (Irving, q1 de #1043).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_plan_segmentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bloque_id')->constrained('ipv6_bloques')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('ipv6_routers')->cascadeOnDelete();

            $table->enum('tipo', ['infraestructura', 'zona_pppoe', 'dedicados', 'enlaces', 'reserva']);
            $table->string('nombre', 150);
            $table->string('prefijo', 100);
            $table->unsignedTinyInteger('longitud_delegacion')->nullable();
            $table->unsignedInteger('vlan_id')->nullable();
            $table->string('interfaz_mikrotik', 100)->nullable();
            $table->string('perfil_ppp', 100)->nullable();
            $table->boolean('ipv6_habilitado')->default(false);

            $table->timestamps();

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_plan_segmentos');
    }
};
