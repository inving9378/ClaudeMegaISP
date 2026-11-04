<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Datos de red del dedicado» (item #988): de dónde y qué se monitorea, POR SERVICIO de internet.
 * Vive en tablas propias a propósito: guardar el servicio (client_internet_services) dispara
 * ClientInternetServiceObserver, que manda trabajos al router; esto nunca toca esa tabla.
 *
 * `servicio_monitoreo` (1 por servicio): router, interfaz VLAN (nombre tal cual, con espacios),
 * VLAN ID, bloque entregado en CIDR (se captura o corrige a mano: el inventario puede no coincidir,
 * p. ej. puede traer la IP del router en vez de la del cliente) y la IP del router dentro del bloque.
 * `servicio_monitoreo_ips`: IPs DEL BLOQUE a las que se hace ping, cada una con su casilla y
 * etiqueta. No se admite una IP suelta fuera del bloque, ni la del router.
 *
 * El interruptor «Cliente dedicado» sigue en client_additional_information.is_dedicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicio_monitoreo')) {
            Schema::create('servicio_monitoreo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->unique()->constrained('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
                $table->string('interfaz_vlan', 191)->nullable()->comment('Nombre de la interfaz VLAN tal cual está en el router (con espacios)');
                $table->unsignedSmallInteger('vlan_id')->nullable()->comment('VLAN ID (1-4094)');
                $table->string('bloque_cidr', 43)->nullable()->comment('Bloque entregado al cliente, p. ej. 203.0.113.120/29');
                $table->string('ip_router', 45)->nullable()->comment('IP del router dentro del bloque (no se monitorea)');
                $table->boolean('activo')->default(true)->comment('false = no se mide este servicio');
                $table->timestamps();
                $table->unique(['router_id', 'interfaz_vlan'], 'servicio_monitoreo_router_interfaz_unique');
                $table->unique(['router_id', 'vlan_id'], 'servicio_monitoreo_router_vlan_unique');
            });
        }

        if (! Schema::hasTable('servicio_monitoreo_ips')) {
            Schema::create('servicio_monitoreo_ips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_monitoreo_id')->constrained('servicio_monitoreo')->cascadeOnDelete();
                $table->string('ip', 45);
                $table->string('etiqueta', 100)->nullable()->comment('Ej. «Equipo principal», «Respaldo»');
                $table->boolean('monitorear')->default(true);
                $table->unsignedTinyInteger('orden')->default(0);
                $table->timestamps();
                $table->unique(['servicio_monitoreo_id', 'ip']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_monitoreo_ips');
        Schema::dropIfExists('servicio_monitoreo');
    }
};
