<?php

namespace Tests\Unit\Metricas;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * Item #988: pruebas del monitoreo que necesitan base. No migran ni tocan datos: crean tablas
 * TEMPORARY en la conexión de la prueba (CreatesApplication ya exige una base *_test), que tapan a
 * las reales y desaparecen al cerrar. Solo las columnas que usa el monitoreo.
 */
abstract class TablasTemporalesTestCase extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config(['metricas.activo' => null, 'metricas.driver' => 'simulado']);
        $this->temporal('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        $this->temporal('activity_log', function (Blueprint $t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description');
            $t->string('subject_type')->nullable();
            $t->string('event')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->json('properties')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
        $this->temporal('clients', function (Blueprint $t) {
            $t->id();
            $t->softDeletes();
        });
        $this->temporal('client_additional_information', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->string('category')->nullable();
            $t->string('tipo_cliente')->default('residencial');
            $t->unsignedBigInteger('segmento_id')->nullable();
            $t->boolean('is_dedicated')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        $this->temporal('client_main_information', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->string('estado')->default('Activo');
            $t->softDeletes();
        });
        $this->temporal('client_internet_services', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->string('estado')->default('Activo');
            $t->unsignedBigInteger('router_id')->nullable();
            $t->unsignedBigInteger('internet_id')->nullable();
            $t->string('ipv4_assignment')->nullable();
            $t->string('ipv4')->nullable();
            $t->integer('ipv4_pool')->nullable();
            $t->string('description')->nullable();
        });
        $this->temporal('internets', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->unsignedBigInteger('download_speed')->nullable();
            $t->unsignedBigInteger('upload_speed')->nullable();
        });
        $this->temporal('networks', function (Blueprint $t) {
            $t->id();
            $t->string('network');
            $t->unsignedTinyInteger('bm');
            $t->string('title')->nullable();
        });
        $this->temporal('network_ips', function (Blueprint $t) {
            $t->id();
            $t->string('ip');
            $t->unsignedBigInteger('network_id')->nullable();
        });
        $this->temporal('servicio_monitoreo_pausas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servicio_id');
            $t->unsignedBigInteger('cliente_id');
            $t->string('motivo', 40);
            $t->dateTime('desde');
            $t->dateTime('hasta')->nullable();
        });
        $this->temporal('routers', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->string('ip_host')->nullable();
            $t->string('nas_ip')->nullable();
            $t->timestamps();
        });
        $this->temporal('mikrotiks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('router_id');
            $t->string('login_api')->nullable();
            $t->string('password_api')->nullable();
            $t->unsignedInteger('port_api')->nullable();
            $t->string('login_api_ro')->nullable();
            $t->text('password_api_ro')->nullable();
            $t->string('identidad_esperada')->nullable();
        });
        $this->temporal('servicio_monitoreo', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servicio_id')->unique();
            $t->unsignedBigInteger('cliente_id');
            $t->unsignedBigInteger('router_id')->nullable();
            $t->string('interfaz_vlan')->nullable();
            $t->unsignedSmallInteger('vlan_id')->nullable();
            $t->string('bloque_cidr')->nullable();
            $t->string('ip_router')->nullable();
            $t->boolean('activo')->default(true);
            $t->boolean('automatica')->default(false);
            $t->string('motivo_pausa', 40)->nullable();
            $t->unsignedBigInteger('revisado_por')->nullable();
            $t->dateTime('revisado_at')->nullable();
            $t->timestamps();
        });
        $this->temporal('servicio_monitoreo_ips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servicio_monitoreo_id');
            $t->string('ip');
            $t->string('etiqueta')->nullable();
            $t->boolean('monitorear')->default(true);
            $t->unsignedTinyInteger('orden')->default(0);
            $t->timestamps();
        });
        $this->temporal('cliente_metricas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servicio_id');
            $t->unsignedBigInteger('cliente_id');
            $t->dateTime('ts');
            $t->unsignedBigInteger('bajada_bps')->nullable();
            $t->unsignedBigInteger('subida_bps')->nullable();
            $t->unsignedInteger('bajada_pps')->nullable();
            $t->unsignedInteger('subida_pps')->nullable();
            $t->string('estado', 32);
            $t->unsignedTinyInteger('ping_ips')->nullable();
            $t->unsignedTinyInteger('ping_responden')->nullable();
            $t->decimal('lat_avg_ms', 9, 3)->nullable();
            $t->decimal('lat_max_ms', 9, 3)->nullable();
            $t->decimal('loss_pct', 5, 2)->nullable();
            $t->string('estado_ping', 16)->nullable();
            $t->string('origen', 16)->default('mikrotik');
            $t->unique(['servicio_id', 'ts']);
        });
        $this->temporal('cliente_metricas_ping', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servicio_id');
            $t->unsignedBigInteger('cliente_id');
            $t->unsignedBigInteger('monitoreo_ip_id')->nullable();
            $t->string('ip', 45);
            $t->dateTime('ts');
            $t->unsignedTinyInteger('enviados');
            $t->unsignedTinyInteger('recibidos');
            $t->decimal('lat_min_ms', 9, 3)->nullable();
            $t->decimal('lat_avg_ms', 9, 3)->nullable();
            $t->decimal('lat_max_ms', 9, 3)->nullable();
            $t->string('estado', 24);
            $t->string('origen', 16)->default('mikrotik');
            $t->unique(['servicio_id', 'ip', 'ts']);
        });
        $this->temporal('cliente_metricas_contadores', function (Blueprint $t) {
            $t->unsignedBigInteger('servicio_id')->primary();
            $t->unsignedBigInteger('cliente_id');
            $t->unsignedBigInteger('router_id')->nullable();
            $t->string('interfaz')->nullable();
            $t->unsignedBigInteger('rx_byte');
            $t->unsignedBigInteger('tx_byte');
            $t->unsignedBigInteger('rx_packet');
            $t->unsignedBigInteger('tx_packet');
            $t->dateTime('leido_at', 3);
            $t->string('origen', 16)->default('mikrotik');
        });
        $this->temporal('metricas_colector_latidos', function (Blueprint $t) {
            $t->id();
            $t->dateTime('ts');
            $t->string('driver', 16);
            $t->unsignedInteger('duracion_ms')->default(0);
            $t->unsignedSmallInteger('routers_ok')->default(0);
            $t->unsignedSmallInteger('routers_error')->default(0);
            $t->unsignedInteger('clientes_ok')->default(0);
            $t->unsignedInteger('clientes_error')->default(0);
            $t->unsignedInteger('clientes_incompletos')->default(0);
            $t->json('detalle')->nullable();
        });
    }

    protected function temporal(string $tabla, callable $columnas): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS `' . $tabla . '`');
        Schema::create($tabla, function (Blueprint $t) use ($columnas) {
            $t->temporary();
            $columnas($t);
        });
    }

    /** Un dedicado con su router y sus datos de red, listo para medirse. @return int id del servicio */
    protected function dedicado(string $titulo = 'Router de prueba', ?string $identidad = null, ?int $vlan = 3001, array $ips = ['192.0.2.10']): int
    {
        $cliente = DB::table('clients')->insertGetId([]);
        DB::table('client_main_information')->insert(['client_id' => $cliente]);
        DB::table('client_additional_information')->insert(['client_id' => $cliente, 'tipo_cliente' => 'dedicado', 'is_dedicated' => true]);
        $router = DB::table('routers')->insertGetId(['title' => $titulo, 'ip_host' => '192.0.2.1']);
        $servicio = DB::table('client_internet_services')->insertGetId(['client_id' => $cliente, 'router_id' => $router]);
        DB::table('mikrotiks')->insert(['router_id' => $router, 'login_api' => 'api', 'password_api' => 'x', 'port_api' => 8728, 'identidad_esperada' => $identidad]);
        $config = DB::table('servicio_monitoreo')->insertGetId([
            'servicio_id' => $servicio, 'cliente_id' => $cliente, 'router_id' => $router, 'vlan_id' => $vlan, 'activo' => true,
        ]);
        foreach ($ips as $i => $ip) {
            DB::table('servicio_monitoreo_ips')->insert(['servicio_monitoreo_id' => $config, 'ip' => $ip, 'monitorear' => true, 'orden' => $i]);
        }

        return $servicio;
    }

    /** @return array{cliente_metricas:int, cliente_metricas_ping:int, cliente_metricas_contadores:int, metricas_colector_latidos:int} */
    protected function lecturasGuardadas(): array
    {
        $cuentas = [];
        foreach (['cliente_metricas', 'cliente_metricas_ping', 'cliente_metricas_contadores', 'metricas_colector_latidos'] as $tabla) {
            $cuentas[$tabla] = DB::table($tabla)->count();
        }

        return $cuentas;
    }
}
