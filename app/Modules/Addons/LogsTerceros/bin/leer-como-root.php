<?php

/**
 * Logs de terceros — lector que corre COMO ROOT, solo para los archivos que el usuario del
 * servidor web no puede leer. Lo lanza Services/LectorComoRoot.php por la misma vía que los
 * add-ons (sudo o su con la contraseña que se escribe en la página); no se usa a mano.
 *
 *   php leer-como-root.php <salida> <uid> <json>
 *
 *   <json>   {"modo": "leer"|"copiar", "ruta": "...", "hasta": n|null, "despues": n|null, "completo": bool}
 *   <salida> archivo NUEVO dentro de <MegaISP>/storage/app/logs-terceros/ donde se deja el
 *            resultado (JSON para «leer»; para «copiar», la copia, o el motivo en <salida>.json
 *            si no se pudo); se le da al <uid>.
 *
 * Solo lee: usa LectorLogs, con las mismas reglas (nunca .env, llaves, /proc…), y no escribe
 * nada fuera de <salida>. Sin Laravel: no se arranca la aplicación como root.
 */

namespace App\Modules\Addons\LogsTerceros\Bin;

use App\Modules\Addons\LogsTerceros\Services\LectorLogs;

require __DIR__ . '/../Services/LectorLogs.php';

const MAXIMO_COPIA = 104857600;   // 100 MB: más grande no se descarga desde la página

function salir(string $mensaje, int $codigo = 3): never
{
    fwrite(STDERR, "leer-como-root: {$mensaje}\n");
    exit($codigo);
}

[, $salida, $uid, $json] = $argv + [null, null, null, null];
if (! is_string($salida) || ! is_string($uid) || ! ctype_digit($uid) || ! is_string($json)) {
    salir('uso: php leer-como-root.php <salida> <uid> <json>');
}
$pedido = json_decode($json, true);
if (! is_array($pedido) || ! is_string($pedido['ruta'] ?? null) || ! in_array($pedido['modo'] ?? '', ['leer', 'copiar'], true)) {
    salir('pedido no válido');
}

// La salida solo puede ser un archivo nuevo en la carpeta de trabajo del módulo.
$carpeta = realpath(dirname(__DIR__, 5) . '/storage/app/logs-terceros');
if ($carpeta === false || realpath(dirname($salida)) !== $carpeta
    || ! preg_match('/^[a-f0-9]{32}(\.json)?$/', basename($salida)) || file_exists($salida)) {
    salir('salida no válida');
}

$lector = new LectorLogs();
$resultado = null;
try {
    if ($pedido['modo'] === 'leer') {
        $r = $lector->leer(
            $pedido['ruta'],
            hasta: isset($pedido['hasta']) ? (int) $pedido['hasta'] : null,
            despues: isset($pedido['despues']) ? (int) $pedido['despues'] : null,
            completo: (bool) ($pedido['completo'] ?? false),
        );
        $resultado = ['ok' => true, 'r' => $r];
    } else {
        $info = $lector->info($pedido['ruta']);
        if (! $info['legible']) {
            throw new \RuntimeException($info['motivo'] ?? 'No se puede leer.');
        }
        if ($info['tamano'] > MAXIMO_COPIA) {
            throw new \LengthException('Pesa ' . $lector->legible($info['tamano']) . ': como root se descarga hasta ' . $lector->legible(MAXIMO_COPIA) . '.');
        }
        $de = fopen($info['real'], 'rb');
        $a = @fopen($salida, 'xb');
        if ($de === false || $a === false) {
            throw new \RuntimeException('No se pudo copiar.');
        }
        stream_copy_to_stream($de, $a);
        fclose($de);
        fclose($a);
        chown($salida, (int) $uid);
        chmod($salida, 0600);
        exit(0);
    }
} catch (\LengthException $e) {
    $resultado = ['ok' => false, 'tipo' => 'grande', 'error' => $e->getMessage()];
} catch (\RuntimeException $e) {
    $resultado = ['ok' => false, 'tipo' => 'error', 'error' => $e->getMessage()];
}

// «copiar» que no se pudo: el motivo va junto, en <salida>.json, y nunca en lugar de la copia.
$destino = $pedido['modo'] === 'copiar' ? $salida . '.json' : $salida;
$f = @fopen($destino, 'xb');
if ($f === false) {
    salir('no se pudo escribir la salida');
}
fwrite($f, (string) json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
fclose($f);
chown($destino, (int) $uid);
chmod($destino, 0600);
exit(0);
