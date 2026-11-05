<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Asistente\Herramientas\CrearCliente;
use App\Services\Metricas\AsegurarMonitoreoDedicados;
use App\Services\Metricas\LecturaPing;
use App\Services\Metricas\ResumenPing;
use App\Services\Metricas\SimuladoMetricasDriver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SOLO DEV (item #988): un cliente dedicado de prueba, con datos genéricos, para ver en pantalla cada
 * estado del monitoreo con el driver simulado. Bloqueado en producción. Se puede volver a correr.
 *
 *   php artisan db:seed --class=MetricasPruebaSeeder
 *
 * Servicios: A con 48 h de lecturas (caída, hueco del colector, pérdida y latencia altas, y una pausa),
 * B con un router sin confirmar, C con un router al que le cambió la identidad, D y E con la VLAN no
 * encontrada y repetida, F automático midiendo la IP del router, G con el servicio pendiente y H libre
 * para probar el botón Guardar sin alterar los demás casos.
 */
class MetricasPruebaSeeder extends Seeder
{
    private const CORREO = 'dedicado.prueba@example.com';

    private const MINUTOS = 48 * 60;

    private const PAQUETES = 3;

    public function run(): void
    {
        if (! SimuladoMetricasDriver::permitido()) {
            $this->command?->error('Bloqueado: los datos de prueba no se siembran en producción.');

            return;
        }

        $cliente = $this->cliente();
        $routers = [
            'A' => $this->router('Router de pruebas A', '192.0.2.1', null),
            'B' => $this->router('Router de pruebas B', '192.0.2.2', null),
            'C' => $this->router('Router de pruebas C', '192.0.2.3', 'router-c-original'),
        ];
        $plan = $this->plan();
        $ift = DB::table('ifts')->where('name', '500 MB')->value('id');
        if ($ift) {
            DB::table('client_main_information')->where('client_id', $cliente)->update(['ift' => $ift]);
        }

        $a = $this->servicio($cliente, $plan, 'Prueba A · con lecturas', $routers['A']);
        $this->monitoreo($a, $cliente, $routers['A'], 3001, '203.0.113.16/29', '203.0.113.17', ['203.0.113.18' => 'Equipo principal', '203.0.113.19' => 'Respaldo']);
        $this->lecturas($a, $cliente, ['203.0.113.18', '203.0.113.19']);

        $b = $this->servicio($cliente, $plan, 'Prueba B · router sin confirmar', $routers['B']);
        $this->monitoreo($b, $cliente, $routers['B'], 3002, '203.0.113.24/29', null, ['203.0.113.26' => 'Equipo']);

        $c = $this->servicio($cliente, $plan, 'Prueba C · cambió la identidad', $routers['C']);
        $this->monitoreo($c, $cliente, $routers['C'], 3003, '203.0.113.32/29', null, ['203.0.113.34' => 'Equipo']);

        $d = $this->servicio($cliente, $plan, 'Prueba D · VLAN no encontrada', $routers['A']);
        $this->monitoreo($d, $cliente, $routers['A'], (int) config('metricas.simulado.vlan_no_encontrada'), '203.0.113.40/29', null, ['203.0.113.42' => 'Equipo']);

        $e = $this->servicio($cliente, $plan, 'Prueba E · VLAN repetida', $routers['A']);
        $this->monitoreo($e, $cliente, $routers['A'], (int) config('metricas.simulado.vlan_repetida'), '203.0.113.48/29', null, ['203.0.113.50' => 'Equipo']);

        // F y G no se configuran aquí: los crea solos AsegurarMonitoreoDedicados, como a cualquier dedicado.
        $h = $this->servicio($cliente, $plan, 'Prueba H · para probar Guardar', $routers['A']);
        $this->monitoreo($h, $cliente, $routers['A'], 3008, '203.0.113.56/29', null, ['203.0.113.58' => 'Equipo']);

        $f = $this->servicio($cliente, $plan, 'Prueba F · automática, mide al router', $routers['A'], 'Activo', $this->ipRegistrada('203.0.113.8', 29, '203.0.113.9'));
        $g = $this->servicio($cliente, $plan, 'Prueba G · servicio pendiente', $routers['A'], 'Pendiente', $this->ipRegistrada('203.0.113.8', 29, '203.0.113.11'));
        DB::table('servicio_monitoreo_pausas')->whereIn('servicio_id', [$f, $g])->delete();
        DB::table('servicio_monitoreo')->whereIn('servicio_id', [$f, $g])->delete();

        $r = app(AsegurarMonitoreoDedicados::class)->cliente($cliente);
        $this->command?->info("Cliente de prueba #{$cliente}. Automáticos creados: {$r['creados']} · pausados: {$r['pausados']}.");
    }

    /** Por el mismo camino que la pantalla Crear cliente. Queda Activo para que se mida. */
    private function cliente(): int
    {
        $existente = DB::table('client_main_information')->where('email', self::CORREO)->value('client_id');
        if ($existente) {
            return (int) $existente;
        }

        $admin = User::role('super-administrator')->firstOrFail();
        auth()->setUser($admin);
        $herramienta = app(CrearCliente::class);
        $preparado = $herramienta->preparar($admin, [
            'nombre' => 'Cliente Dedicado', 'apellido_paterno' => 'De', 'apellido_materno' => 'Prueba', 'correo' => self::CORREO,
            'curp' => 'PRUEBA000000HDFXXX00', 'plazo_meses' => 12, 'colonia_id' => DB::table('colonies')->value('id'),
            'costo_activacion' => 0, 'tipo_cliente' => 'dedicado', 'aunque_exista' => true,
        ]);
        if (isset($preparado['error'])) {
            throw new RuntimeException($preparado['error']);
        }
        $alta = $herramienta->ejecutar($admin, $preparado['argumentos']);
        if (empty($alta['ok'])) {
            throw new RuntimeException($alta['error'] ?? 'No se pudo crear el cliente de prueba.');
        }

        // Sin eventos: activar por el modelo manda trabajos al router.
        DB::table('client_main_information')->where('client_id', $alta['sujeto']->id)->update(['estado' => 'Activo']);

        return (int) $alta['sujeto']->id;
    }

    /** Un plan propio, con velocidad y nombre que coinciden, copiando el resto de los campos de uno existente. */
    private function plan(): int
    {
        $titulo = 'Plan de prueba 500 Mbps';
        $id = DB::table('internets')->where('title', $titulo)->value('id');
        if ($id) {
            return (int) $id;
        }
        $base = (array) DB::table('internets')->orderBy('id')->first();
        unset($base['id']);

        return (int) DB::table('internets')->insertGetId(array_merge($base, [
            'title' => $titulo, 'service_name' => $titulo, 'download_speed' => 500000, 'upload_speed' => 500000,
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    /** Directo a las tablas: guardar el modelo Mikrotik escribe en el router. */
    private function router(string $titulo, string $host, ?string $identidad): int
    {
        $id = DB::table('routers')->where('title', $titulo)->value('id')
            ?? DB::table('routers')->insertGetId(['title' => $titulo, 'ip_host' => $host, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mikrotiks')->updateOrInsert(['router_id' => $id], ['login_api' => 'simulado', 'port_api' => 8728, 'identidad_esperada' => $identidad, 'updated_at' => now()]);

        return (int) $id;
    }

    private function ipRegistrada(string $red, int $bm, string $ip): int
    {
        $idRed = DB::table('networks')->where('network', $red)->where('bm', $bm)->value('id')
            ?? DB::table('networks')->insertGetId(['network' => $red, 'bm' => $bm, 'title' => 'Red de pruebas', 'created_at' => now(), 'updated_at' => now()]);

        return (int) (DB::table('network_ips')->where('network_id', $idRed)->where('ip', $ip)->value('id')
            ?? DB::table('network_ips')->insertGetId(['ip' => $ip, 'network_id' => $idRed, 'created_at' => now(), 'updated_at' => now()]));
    }

    /** Directo a la tabla: guardar el modelo del servicio manda trabajos al router. */
    private function servicio(int $cliente, ?int $plan, string $descripcion, int $router, string $estado = 'Activo', ?int $ip = null): int
    {
        $datos = ['internet_id' => $plan, 'amount' => 1, 'price' => 0, 'pay_period' => 'Mensual', 'discount' => 0, 'estado' => $estado, 'router_id' => $router,
            'ipv4_assignment' => 'IP Estatica', 'ipv4' => $ip, 'start_date' => now()->subDays(3)->format('Y-m-d'), 'updated_at' => now()];
        $id = DB::table('client_internet_services')->where('client_id', $cliente)->where('description', $descripcion)->value('id');
        if ($id) {
            DB::table('client_internet_services')->where('id', $id)->update($datos);

            return (int) $id;
        }

        return (int) DB::table('client_internet_services')->insertGetId($datos + [
            'client_id' => $cliente, 'description' => $descripcion, 'client_name' => 'prueba-dedicado-' . substr($descripcion, 7, 1), 'created_at' => now(),
        ]);
    }

    /** @param  array<string, string>  $ips  IP => etiqueta */
    private function monitoreo(int $servicio, int $cliente, int $router, int $vlan, string $bloque, ?string $ipRouter, array $ips): void
    {
        DB::table('servicio_monitoreo')->updateOrInsert(['servicio_id' => $servicio], [
            'cliente_id' => $cliente, 'router_id' => $router, 'vlan_id' => $vlan, 'bloque_cidr' => $bloque, 'ip_router' => $ipRouter,
            'activo' => true, 'automatica' => false, 'revisado_at' => now(), 'updated_at' => now(),
        ]);
        $config = DB::table('servicio_monitoreo')->where('servicio_id', $servicio)->value('id');
        $orden = 0;
        foreach ($ips as $ip => $etiqueta) {
            DB::table('servicio_monitoreo_ips')->updateOrInsert(['servicio_monitoreo_id' => $config, 'ip' => $ip], ['etiqueta' => $etiqueta, 'monitorear' => true, 'orden' => $orden++, 'updated_at' => now()]);
        }
    }

    /** 48 h de lecturas simuladas, con los casos que la pantalla tiene que saber mostrar. Borra antes solo lo simulado. */
    private function lecturas(int $servicio, int $cliente, array $ips): void
    {
        $fin = Carbon::now()->startOfMinute()->subMinute();
        $inicio = $fin->copy()->subMinutes(self::MINUTOS - 1);
        $idsIp = DB::table('servicio_monitoreo_ips as i')->join('servicio_monitoreo as m', 'm.id', '=', 'i.servicio_monitoreo_id')
            ->where('m.servicio_id', $servicio)->pluck('i.id', 'i.ip');
        $semilla = SimuladoMetricasDriver::semilla($servicio, null, 3001);
        $ventana = fn (int $horasAtras, int $duracion) => [self::MINUTOS - $horasAtras * 60, self::MINUTOS - $horasAtras * 60 + $duracion];
        $dentro = fn (int $m, array $v) => $m >= $v[0] && $m < $v[1];

        $caida = $ventana(30, 20);
        $routerCaido = $ventana(20, 6);
        $latenciaAlta = $ventana(9, 12);
        $perdidaAlta = $ventana(5, 8);
        $hueco = $ventana(4, 15);
        $pausa = $ventana(3, 40);

        DB::table('cliente_metricas')->where('servicio_id', $servicio)->where('origen', 'simulado')->delete();
        DB::table('cliente_metricas_ping')->where('servicio_id', $servicio)->where('origen', 'simulado')->delete();
        DB::table('cliente_metricas_contadores')->where('servicio_id', $servicio)->delete();
        DB::table('servicio_monitoreo_pausas')->where('servicio_id', $servicio)->delete();
        DB::table('servicio_monitoreo_pausas')->insert([
            'servicio_id' => $servicio, 'cliente_id' => $cliente, 'motivo' => 'cliente_bloqueado',
            'desde' => $inicio->copy()->addMinutes($pausa[0]), 'hasta' => $inicio->copy()->addMinutes($pausa[1]),
        ]);

        $filas = $pings = [];
        for ($m = 0; $m < self::MINUTOS; $m++) {
            if ($dentro($m, $hueco) || $dentro($m, $pausa)) {
                continue;
            }
            $ts = $inicio->copy()->addMinutes($m);
            $texto = $ts->format('Y-m-d H:i:s');
            $t = $ts->getTimestamp() + 30;
            $cayo = $dentro($m, $caida);
            $sinRouter = $dentro($m, $routerCaido);

            $fila = ['servicio_id' => $servicio, 'cliente_id' => $cliente, 'ts' => $texto, 'origen' => 'simulado',
                'estado' => 'ok', 'bajada_bps' => null, 'subida_bps' => null, 'bajada_pps' => null, 'subida_pps' => null];
            if ($sinRouter) {
                $fila['estado'] = 'router_sin_conexion';
            } elseif ($cayo) {
                $fila = ['estado' => 'interfaz_caida', 'bajada_bps' => 0, 'subida_bps' => 0, 'bajada_pps' => 0, 'subida_pps' => 0] + $fila;
            } else {
                $bajada = SimuladoMetricasDriver::tasaBps($semilla, 'bajada', $t);
                $subida = SimuladoMetricasDriver::tasaBps($semilla, 'subida', $t);
                $fila = ['bajada_bps' => $bajada, 'subida_bps' => $subida, 'bajada_pps' => SimuladoMetricasDriver::ppsDe($bajada, 'bajada'), 'subida_pps' => SimuladoMetricasDriver::ppsDe($subida, 'subida')] + $fila;
            }

            $lecturas = [];
            foreach ($ips as $ip) {
                $l = match (true) {
                    $sinRouter => null,
                    $cayo      => new LecturaPing(self::PAQUETES, []),
                    default    => SimuladoMetricasDriver::ping($ip, $t, self::PAQUETES),
                };
                if ($l?->responde() && $dentro($m, $latenciaAlta)) {
                    $l = new LecturaPing(self::PAQUETES, array_map(fn ($r) => round($r + 4, 3), $l->rttsMs));
                }
                if ($l?->responde() && $dentro($m, $perdidaAlta)) {
                    $l = new LecturaPing(self::PAQUETES, array_slice($l->rttsMs, 0, self::PAQUETES - 1));
                }
                $lecturas[] = $l;
                $pings[] = ['servicio_id' => $servicio, 'cliente_id' => $cliente, 'monitoreo_ip_id' => $idsIp[$ip] ?? null, 'ip' => $ip, 'ts' => $texto,
                    'enviados' => self::PAQUETES, 'recibidos' => $l?->recibidos() ?? 0, 'lat_min_ms' => $l?->minMs(), 'lat_avg_ms' => $l?->avgMs(), 'lat_max_ms' => $l?->maxMs(),
                    'estado' => $l === null ? 'omitido' : ($l->responde() ? 'responde' : 'sin_respuesta'), 'origen' => 'simulado'];
            }
            $filas[] = $fila + ResumenPing::de($lecturas);

            if (count($filas) >= 500) {
                DB::table('cliente_metricas')->insert($filas);
                DB::table('cliente_metricas_ping')->insert($pings);
                $filas = $pings = [];
            }
        }
        if ($filas !== []) {
            DB::table('cliente_metricas')->insert($filas);
            DB::table('cliente_metricas_ping')->insert($pings);
        }
    }
}
