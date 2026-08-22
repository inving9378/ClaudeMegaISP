<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1043 (IPv6 1.2a, sub-item de #985/#811 sección 1.2) — andamiaje
 * aditivo de infraestructura IPv6: routers MikroTik gestionables. Solo
 * modelo de datos, sin conexión real a RouterOS todavía (eso es fase
 * aparte). Path database/migrations/ (no app/Modules/Addons/Ipv6/migrations/)
 * porque #984 (scaffold del ModuleServiceProvider) aún no está mergeado a
 * main; mismo patrón ya usado por los items hermanos #992/#1064/#1065.
 * Modelo ya mergeado: app/Modules/Addons/Ipv6/Models/Ipv6Router.php
 * (Model plano, sin created_by/updated_by).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv6_routers', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150);
            $table->string('host_api', 150);
            $table->unsignedInteger('puerto_api')->nullable();
            $table->string('version_routeros', 50)->nullable();
            $table->string('board_name', 100)->nullable();
            $table->string('driver_familia', 100)->nullable();
            $table->boolean('version_manual')->default(false);
            $table->timestamp('ultimo_contacto')->nullable();
            $table->enum('estado', ['activo', 'inactivo', 'sin_contacto'])->default('sin_contacto');

            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv6_routers');
    }
};
