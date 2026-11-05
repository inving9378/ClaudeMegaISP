<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rotación de la clave maestra (APP_KEY): re-cifra TODOS los valores guardados cifrados (item #1085).
 *
 * Laravel 10 no acepta una clave anterior junto a la nueva, así que se re-cifra de una vez.
 *
 *   php artisan seguridad:recifrar                        # F0/F1: inventario + ensayo EN MEMORIA (no escribe nada)
 *   php artisan seguridad:recifrar --apply --clave-nueva-archivo=/ruta/clave600.txt
 *   php artisan seguridad:recifrar --revertir=/ruta/respaldo.json
 *
 * Seguridad:
 *  - La clave nueva se lee SOLO de un archivo con permisos 600 (nunca de la línea de comandos ni del log).
 *    Formato igual a APP_KEY: `base64:...` o 32 bytes crudos.
 *  - Nunca imprime valores ni claves; solo conteos y huellas (sha256 truncado).
 *  - Los valores que NO descifran con la clave actual se dejan intactos y se reportan (ya eran ilegibles).
 *  - Con --apply: respaldo 0600 de las columnas tocadas, una transacción, y verificación de que TODO
 *    descifra con la clave nueva antes de confirmar (si no, rollback).
 *  - NO toca el .env: cambiar APP_KEY, config:clear y queue:restart es un paso manual posterior.
 *
 * Preserva la forma del valor: descifra y cifra con serialize=false (lo que usan encryptString y el cast
 * `encrypted`), de modo que el texto en claro resultante es byte-idéntico.
 */
class RecifrarSecretosCommand extends Command
{
    protected $signature = 'seguridad:recifrar
        {--apply : Escribe de verdad (sin esto solo inventario + ensayo en memoria)}
        {--clave-nueva-archivo= : Archivo (permisos 600) con la APP_KEY nueva}
        {--respaldo-dir= : Carpeta del respaldo (default storage/app/private/seguridad)}
        {--revertir= : Restaura las columnas desde un respaldo JSON generado por --apply}';

    protected $description = 'Re-cifra los secretos guardados en BD con una APP_KEY nueva (item #1085). Por defecto solo ensayo.';

    /** [tabla, pk, columna, filtro where opcional [col, valor]] */
    private const OBJETIVOS = [
        ['api_integrations', 'id', 'encrypted_value', null],
        ['payment_providers', 'id', 'config', null],
        ['voip_configuracion', 'id', 'sip_secret', null],
        ['voip_extensiones', 'id', 'secret', null],
        ['voip_troncales', 'id', 'secret', null],
        ['whatsapp_instances', 'id', 'api_key', null],
        ['ia_proveedores', 'id', 'api_key', null],
        ['olt_smartolt_config', 'id', 'api_token', null],
        ['marketing_settings', 'id', 'value', ['encrypted', 1]],
        ['megafamilia_settings', 'key', 'value', ['encrypted', 1]],
        ['talento_credentials', 'id', 'file_path', null],
    ];

    public function handle(): int
    {
        if ($this->option('revertir')) {
            return $this->revertir((string) $this->option('revertir'));
        }

        $actual = $this->encrypterActual();
        $nueva = $this->encrypterNueva();
        if ($nueva === false) {
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && $nueva === null) {
            $this->error('--apply exige --clave-nueva-archivo.');
            return self::FAILURE;
        }

        $this->line('Huella clave actual: ' . $this->huella(config('app.key')));
        if ($nueva) {
            $this->line('Huella clave nueva:   ' . $this->huella(trim((string) file_get_contents($this->option('clave-nueva-archivo')))));
        }

        $plan = []; // [tabla, pk, col, id, nuevoValor, viejoValor]
        $resumen = [];
        foreach (self::OBJETIVOS as [$tabla, $pk, $col, $filtro]) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, $col)) {
                $resumen[] = [$tabla . '.' . $col, '(no existe)', '-', '-', '-'];
                continue;
            }
            $q = DB::table($tabla)->whereNotNull($col)->where($col, '!=', '');
            if ($filtro && Schema::hasColumn($tabla, $filtro[0])) {
                $q->where($filtro[0], $filtro[1]);
            }
            $forma = $descifra = $ilegible = $ok = 0;
            foreach ($q->select($pk, $col)->get() as $fila) {
                $valor = $fila->$col;
                if (!$this->pareceCifrado($valor)) {
                    continue; // texto plano u otra cosa: no es de este comando
                }
                $forma++;
                try {
                    $claro = $actual->decrypt($valor, false);
                } catch (\Throwable) {
                    $ilegible++;
                    continue;
                }
                $descifra++;
                if ($nueva) {
                    $nuevoValor = $nueva->encrypt($claro, false);
                    if ($nueva->decrypt($nuevoValor, false) === $claro) {
                        $ok++;
                        $plan[] = [$tabla, $pk, $col, $fila->$pk, $nuevoValor, $valor];
                    }
                }
            }
            $resumen[] = [$tabla . '.' . $col, $forma, $descifra, $ilegible, $nueva ? $ok : 'n/a'];
        }

        $this->table(['Columna', 'Con forma de cifrado', 'Descifran (actual)', 'Ilegibles (se dejan)', 'Re-cifrado OK'], $resumen);

        if (!$apply) {
            $this->info('ENSAYO: no se escribió nada.' . ($nueva ? '' : ' (Pasa --clave-nueva-archivo para ensayar el re-cifrado.)'));
            return self::SUCCESS;
        }

        return $this->aplicar($plan);
    }

    private function aplicar(array $plan): int
    {
        $dir = $this->option('respaldo-dir') ?: storage_path('app/private/seguridad');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $ruta = rtrim($dir, '/') . '/recifrar-' . date('Ymd-His') . '.json';
        $fh = fopen($ruta, 'x');
        chmod($ruta, 0600);
        fwrite($fh, json_encode(array_map(fn ($p) => [$p[0], $p[1], $p[2], $p[3], $p[5]], $plan)));
        fclose($fh);
        $this->line('Respaldo (0600): ' . $ruta . ' — contiene los valores cifrados con la clave ACTUAL.');

        $nueva = $this->encrypterNueva();
        try {
            DB::transaction(function () use ($plan, $nueva) {
                foreach ($plan as [$tabla, $pk, $col, $id, $nuevoValor]) {
                    DB::table($tabla)->where($pk, $id)->update([$col => $nuevoValor]);
                }
                foreach ($plan as [$tabla, $pk, $col, $id, $nuevoValor]) {
                    $guardado = DB::table($tabla)->where($pk, $id)->value($col);
                    $nueva->decrypt($guardado, false); // lanza si no descifra
                }
            });
        } catch (\Throwable $e) {
            $this->error('Falló y se hizo rollback: ' . get_class($e));
            return self::FAILURE;
        }

        $this->info(count($plan) . ' valores re-cifrados y verificados con la clave nueva.');
        $this->warn('PENDIENTE (manual): cambiar APP_KEY en el .env (revisar grupo www-data), config:clear y queue:restart.');
        return self::SUCCESS;
    }

    private function revertir(string $ruta): int
    {
        if (!is_file($ruta)) {
            $this->error('Respaldo no encontrado.');
            return self::FAILURE;
        }
        $filas = json_decode((string) file_get_contents($ruta), true);
        if (!is_array($filas)) {
            $this->error('Respaldo inválido.');
            return self::FAILURE;
        }
        $validas = array_column(self::OBJETIVOS, null, 0);
        DB::transaction(function () use ($filas, $validas) {
            foreach ($filas as [$tabla, $pk, $col, $id, $viejo]) {
                // Solo columnas de la lista blanca: el respaldo no puede apuntar a otra tabla.
                if (!isset($validas[$tabla]) || $validas[$tabla][1] !== $pk || $validas[$tabla][2] !== $col) {
                    throw new \RuntimeException('Entrada fuera de la lista blanca.');
                }
                DB::table($tabla)->where($pk, $id)->update([$col => $viejo]);
            }
        });
        $this->info(count($filas) . ' valores restaurados. Recuerda volver a poner la APP_KEY anterior en el .env.');
        return self::SUCCESS;
    }

    private function pareceCifrado($valor): bool
    {
        if (!is_string($valor)) {
            return false;
        }
        $j = json_decode((string) base64_decode($valor, true), true);
        return is_array($j) && isset($j['iv'], $j['value'], $j['mac']);
    }

    private function encrypterActual(): Encrypter
    {
        return new Encrypter($this->claveCruda((string) config('app.key')), config('app.cipher'));
    }

    /** @return Encrypter|null|false  null = sin clave nueva; false = error */
    private function encrypterNueva(): Encrypter|null|false
    {
        $archivo = $this->option('clave-nueva-archivo');
        if (!$archivo) {
            return null;
        }
        if (!is_file($archivo) || (fileperms($archivo) & 0077) !== 0) {
            $this->error('La clave nueva debe estar en un archivo existente con permisos 600.');
            return false;
        }
        try {
            return new Encrypter($this->claveCruda(trim((string) file_get_contents($archivo))), config('app.cipher'));
        } catch (\Throwable) {
            $this->error('Clave nueva inválida para el cifrado ' . config('app.cipher') . '.');
            return false;
        }
    }

    private function claveCruda(string $k): string
    {
        $k = trim($k);
        return str_starts_with($k, 'base64:') ? base64_decode(substr($k, 7)) : $k;
    }

    private function huella(string $cruda): string
    {
        return substr(hash('sha256', $cruda), 0, 10);
    }
}
