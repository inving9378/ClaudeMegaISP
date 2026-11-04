<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoreo por minuto de los servicios dedicados (item #988, Fases 2 y 3). Todo va por
 * SERVICIO (un cliente con dos servicios dedicados tiene dos series); `cliente_id` se guarda
 * también para consultar por cliente.
 *
 * `cliente_metricas`: una fila por servicio por minuto.
 *   - Tráfico en la perspectiva del CLIENTE: bajada = tx de su interfaz VLAN en el router,
 *     subida = rx. `estado` explica el hueco cuando no hay velocidad (interfaz caída, router
 *     sin conexión, contador reiniciado...).
 *   - Resumen del ping a sus IPs (`ping_*`, `estado_ping`): en_linea si responden todas,
 *     degradado si responde al menos una pero no todas, sin_respuesta si ninguna. La
 *     disponibilidad es el tiempo con al menos una IP respondiendo.
 * `cliente_metricas_ping`: el detalle del ping por IP y minuto (desde el MikroTik).
 * `cliente_metricas_contadores`: la última lectura cruda de los contadores de cada servicio
 *   (con los nombres del router: rx/tx), base para calcular la siguiente diferencia.
 *
 * El ping viejo (mikrotik:sync-ping / ping_statistics) no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cliente_metricas')) {
            Schema::create('cliente_metricas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->constrained('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->dateTime('ts')->comment('Minuto de la lectura (segundos en 0)');
                $table->unsignedBigInteger('bajada_bps')->nullable()->comment('Bajada del cliente = tx de la VLAN');
                $table->unsignedBigInteger('subida_bps')->nullable()->comment('Subida del cliente = rx de la VLAN');
                $table->unsignedInteger('bajada_pps')->nullable();
                $table->unsignedInteger('subida_pps')->nullable();
                $table->string('estado', 32)->comment('Tráfico: ok | primera_lectura | hueco_largo | contador_reiniciado | lectura_invalida | interfaz_caida | interfaz_no_encontrada | sin_interfaz | router_sin_conexion | router_no_verificado | omitido | error');
                $table->unsignedTinyInteger('ping_ips')->nullable()->comment('IPs del servicio medidas en ese minuto');
                $table->unsignedTinyInteger('ping_responden')->nullable()->comment('Cuántas respondieron');
                $table->decimal('lat_avg_ms', 9, 3)->nullable()->comment('Promedio de las IPs que respondieron');
                $table->decimal('lat_max_ms', 9, 3)->nullable();
                $table->decimal('loss_pct', 5, 2)->nullable()->comment('Pérdida promedio de las IPs medidas');
                $table->string('estado_ping', 16)->nullable()->comment('en_linea | degradado | sin_respuesta (null = no se midió)');
                $table->string('origen', 16)->default('mikrotik')->comment('mikrotik | simulado');
                $table->unique(['servicio_id', 'ts']);
                $table->index(['cliente_id', 'ts']);
            });
        }

        if (! Schema::hasTable('cliente_metricas_ping')) {
            Schema::create('cliente_metricas_ping', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->constrained('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('monitoreo_ip_id')->nullable()->constrained('servicio_monitoreo_ips')->nullOnDelete();
                $table->string('ip', 45)->comment('Copia de la IP medida, por si después se edita la lista');
                $table->dateTime('ts')->comment('Minuto de la medición');
                $table->unsignedTinyInteger('enviados');
                $table->unsignedTinyInteger('recibidos');
                $table->decimal('lat_min_ms', 9, 3)->nullable();
                $table->decimal('lat_avg_ms', 9, 3)->nullable();
                $table->decimal('lat_max_ms', 9, 3)->nullable();
                $table->string('estado', 24)->comment('responde | sin_respuesta | omitido | error');
                $table->string('origen', 16)->default('mikrotik');
                $table->unique(['servicio_id', 'ip', 'ts']);
                $table->index(['monitoreo_ip_id', 'ts']);
            });
        }

        if (! Schema::hasTable('cliente_metricas_contadores')) {
            Schema::create('cliente_metricas_contadores', function (Blueprint $table) {
                $table->unsignedBigInteger('servicio_id')->primary();
                $table->foreign('servicio_id')->references('id')->on('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->unsignedBigInteger('router_id')->nullable();
                $table->string('interfaz', 191)->nullable();
                $table->unsignedBigInteger('rx_byte');
                $table->unsignedBigInteger('tx_byte');
                $table->unsignedBigInteger('rx_packet');
                $table->unsignedBigInteger('tx_packet');
                $table->dateTime('leido_at', 3);
                $table->string('origen', 16)->default('mikrotik');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_metricas_contadores');
        Schema::dropIfExists('cliente_metricas_ping');
        Schema::dropIfExists('cliente_metricas');
    }
};
