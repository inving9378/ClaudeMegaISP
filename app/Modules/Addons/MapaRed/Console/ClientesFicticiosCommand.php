<?php

namespace App\Modules\Addons\MapaRed\Console;

use App\Modules\Addons\MapaRed\Repositories\MapaRedProyectRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * Item #1246 (R0 Fase 1 de #1244) — pobla el Mapa de Red de DEV con clientes FICTICIOS colgados de
 * las cajas reales, sin restaurar datos de nadie. Inserción directa con DB::table (sin observers,
 * sin eventos, sin servicios, sin password) para no encolar nada hacia MikroTik/Asterisk/WhatsApp.
 *
 * Marca inequívoca: clients.is_test_data=1 + name con prefijo "FICTICIO" + email @prueba.meganet.local
 * + user "fic_*". `--borrar` quita exactamente eso y vuelve a desligar sus puertos.
 */
class ClientesFicticiosCommand extends Command
{
    protected $signature = 'mapared:clientes-ficticios {--cantidad=3400} {--dry-run} {--borrar} {--sin-respaldo}';

    protected $description = '#1246: crea (o borra con --borrar) clientes ficticios en el mapa de dev, con respaldo previo';

    public const PREFIJO = 'FICTICIO';
    public const DOMINIO = '@prueba.meganet.local';
    private const DESPLAZAMIENTO_GRADOS = 0.00027; // ≈ 30 m

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando es solo para dev. Abortado en producción.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $antes = $this->lineaBase();
        $this->line('Línea base: '.json_encode($antes));

        $resultado = $this->option('borrar') ? $this->borrar($dry) : $this->crear($dry);
        if ($resultado !== self::SUCCESS) {
            return $resultado;
        }

        if (! $dry) {
            MapaRedProyectRepository::invalidateNodesCache();
            $despues = $this->lineaBase();
            $this->line('Después: '.json_encode($despues));
            foreach (['jobs', 'mikrotik_item_to_excecute_actions', 'notifications'] as $t) {
                if (($antes[$t] ?? null) !== ($despues[$t] ?? null)) {
                    $this->error("⚠ {$t} cambió ({$antes[$t]} → {$despues[$t]}): revisar.");

                    return self::FAILURE;
                }
            }
            $this->info('Verificado: no se encoló nada (jobs, acciones MikroTik, notificaciones intactos).');
        } else {
            $this->warn('--dry-run: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    private function ids(): array
    {
        return DB::table('client_main_information')
            ->where('name', 'like', self::PREFIJO.'%')
            ->where('email', 'like', '%'.self::DOMINIO)
            ->pluck('client_id')
            ->all();
    }

    private function lineaBase(): array
    {
        $r = [
            'clients' => DB::table('clients')->count(),
            'cmi' => DB::table('client_main_information')->count(),
            'ficticios' => count($this->ids()),
            'puertos_con_cliente' => DB::table('mapared_devices_ports')->whereNotNull('client_id')->count(),
        ];
        foreach (['jobs', 'mikrotik_item_to_excecute_actions', 'notifications', 'users', 'activity_log'] as $t) {
            $r[$t] = Schema::hasTable($t) ? DB::table($t)->count() : null;
        }

        return $r;
    }

    private function crear(bool $dry): int
    {
        $meta = max(0, (int) $this->option('cantidad'));
        $existentes = count($this->ids());
        $faltan = max(0, $meta - $existentes);
        $this->line("Meta {$meta}, ya existen {$existentes}, por crear {$faltan}.");
        if ($faltan === 0) {
            $this->info('Nada que crear (idempotente).');

            return self::SUCCESS;
        }

        $cajas = DB::table('mapared_devices')->whereIn('type', ['drop', 'splitter'])
            ->whereNotNull('lat')->whereNotNull('lng')->get(['lat', 'lng']);
        if ($cajas->isEmpty()) {
            $this->error('No hay cajas (drop/splitter) con coordenadas.');

            return self::FAILURE;
        }
        // Puertos libres de dispositivos "client": se ligan primero (coord = su caja padre si existe).
        $libres = DB::table('mapared_devices_ports as p')
            ->join('mapared_devices as d', 'd.id', '=', 'p.device_id')
            ->leftJoin('mapared_devices as c', 'c.id', '=', 'd.parent_id')
            ->where('d.type', 'client')->whereNull('p.client_id')
            ->orderBy('p.id')->limit($faltan)
            ->get(['p.id as port_id', 'c.lat as clat', 'c.lng as clng', 'd.lat as dlat', 'd.lng as dlng']);
        $this->line('Puertos libres a ligar: '.$libres->count().'; solo con punto: '.($faltan - $libres->count()));

        if ($dry) {
            return self::SUCCESS;
        }
        if (! $this->option('sin-respaldo')) {
            $this->respaldar();
        }

        $admin = (int) (DB::table('users')->where('is_system', 1)->value('id') ?? 1);
        $estados = ['Activo', 'Activo', 'Activo', 'Bloqueado', 'Inactivo', 'Nuevo'];
        $ahora = now();
        $sig = $this->siguienteNumero();

        DB::transaction(function () use ($faltan, $libres, $cajas, $admin, $estados, $ahora, &$sig) {
            for ($i = 0; $i < $faltan; $i++, $sig++) {
                $puerto = $libres[$i] ?? null;
                if ($puerto && ($puerto->clat ?? $puerto->dlat) !== null) {
                    $lat = (float) ($puerto->clat ?? $puerto->dlat);
                    $lng = (float) ($puerto->clng ?? $puerto->dlng);
                } else {
                    $c = $cajas[random_int(0, $cajas->count() - 1)];
                    $lat = (float) $c->lat;
                    $lng = (float) $c->lng;
                }
                $lat += $this->jitter();
                $lng += $this->jitter();
                $n = str_pad((string) $sig, 5, '0', STR_PAD_LEFT);

                $clientId = DB::table('clients')->insertGetId([
                    'created_by' => $admin, 'updated_by' => $admin, 'is_test_data' => 1,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                DB::table('client_main_information')->insert([
                    'client_id' => $clientId,
                    'user' => 'fic_'.$n,
                    'estado' => $estados[$i % count($estados)],
                    'name' => self::PREFIJO,
                    'father_last_name' => 'MAPA '.$n,
                    'email' => 'fic_'.$n.self::DOMINIO,
                    'geodata' => sprintf('%.7f,%.7f', $lat, $lng),
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                if ($puerto) {
                    DB::table('mapared_devices_ports')->where('id', $puerto->port_id)->whereNull('client_id')
                        ->update(['client_id' => $clientId, 'updated_at' => $ahora]);
                }
            }
        });

        $total = count($this->ids());
        if ($total !== max($faltan + $existentes, $total) || $total < $existentes + $faltan) {
            $this->error("Conteo inesperado de ficticios: {$total}");

            return self::FAILURE;
        }
        $this->info("Creados {$faltan}; ficticios totales: {$total}.");

        return self::SUCCESS;
    }

    private function borrar(bool $dry): int
    {
        $ids = $this->ids();
        $puertos = $ids ? DB::table('mapared_devices_ports')->whereIn('client_id', $ids)->count() : 0;
        $this->line('Ficticios a borrar: '.count($ids)." (puertos a desligar: {$puertos}).");
        if ($dry || ! $ids) {
            return self::SUCCESS;
        }
        if (! $this->option('sin-respaldo')) {
            $this->respaldar();
        }
        DB::transaction(function () use ($ids) {
            foreach (array_chunk($ids, 500) as $lote) {
                DB::table('mapared_devices_ports')->whereIn('client_id', $lote)->update(['client_id' => null, 'updated_at' => now()]);
                DB::table('client_main_information')->whereIn('client_id', $lote)->delete();
                DB::table('clients')->whereIn('id', $lote)->where('is_test_data', 1)->delete();
            }
        });
        $this->info('Borrados. Ficticios restantes: '.count($this->ids()));

        return self::SUCCESS;
    }

    private function siguienteNumero(): int
    {
        $max = DB::table('client_main_information')->where('user', 'like', 'fic\_%')->max('user');

        return $max ? ((int) substr($max, 4)) + 1 : 1;
    }

    private function jitter(): float
    {
        return (random_int(-1000, 1000) / 1000) * self::DESPLAZAMIENTO_GRADOS;
    }

    /** mysqldump de las tablas tocadas + sha256 en storage/app/backups-mapared-red/. */
    private function respaldar(): void
    {
        $dir = storage_path('app/backups-mapared-red');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $cfg = config('database.connections.'.config('database.default'));
        $archivo = $dir.'/ficticios-'.date('Ymd-His').'.sql.gz';
        $tablas = ['clients', 'client_main_information', 'mapared_devices_ports'];
        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction %s %s | gzip > %s',
            escapeshellarg($cfg['host']), escapeshellarg((string) $cfg['port']), escapeshellarg($cfg['username']),
            escapeshellarg($cfg['database']), implode(' ', array_map('escapeshellarg', $tablas)), escapeshellarg($archivo)
        );
        $p = Process::fromShellCommandline('set -o pipefail; '.$cmd, null, ['MYSQL_PWD' => (string) $cfg['password']], null, 600);
        $p->run();
        if (! $p->isSuccessful() || ! is_file($archivo) || filesize($archivo) < 1000) {
            throw new \RuntimeException('Respaldo previo falló: '.$p->getErrorOutput());
        }
        $sha = hash_file('sha256', $archivo);
        file_put_contents($archivo.'.sha256', $sha.'  '.basename($archivo).PHP_EOL);
        $this->info("Respaldo: {$archivo} sha256={$sha}");
    }
}
