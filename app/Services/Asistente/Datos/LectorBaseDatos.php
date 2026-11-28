<?php

namespace App\Services\Asistente\Datos;

use App\Models\Asistente\Adjunto;
use App\Services\Asistente\AdjuntosAsistente;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * JARVIS consulta cualquier información del sistema, siempre en SOLO LECTURA y sin secretos.
 *
 * Capas (cada una basta para lo suyo; juntas cubren los descuidos y las trampas):
 *  1. La consulta se revisa palabra por palabra: una sola instrucción SELECT/WITH, sin comentarios,
 *     variables, archivos, bloqueos ni otras bases de datos.
 *  2. Corre en una conexión propia en modo READ ONLY de MySQL (rechaza cualquier escritura, incluso
 *     tablas temporales), sin varias instrucciones por llamada y con tiempo máximo.
 *  3. Contraseñas, tokens y llaves: no se pueden nombrar en la consulta; las tablas que las tienen no
 *     aceptan «*»; las tablas que solo guardan secretos o datos internos no se consultan; y en el
 *     resultado se tapan las columnas y las llaves de JSON con nombre de secreto.
 *
 * Las hojas de los Excel adjuntos se pueden usar como tablas (archivo_<id>, archivo_<id>_<hoja>)
 * para cruzarlas con el sistema.
 */
class LectorBaseDatos
{
    private const CONEXION = 'asistente_lectura';

    /** Nombre de columna (o llave JSON) que guarda un secreto. */
    public const SENSIBLE = '/(pass(word|wd)?(_|$)|password|contrase|secret|token|api_?key|apikey|credential|private_?key|cookie|(^|_)otp(_|$)|pin_hash|code_hash|(^|_)salt$|cvv|card_number)/i';

    /** …salvo estos, que se llaman parecido pero no guardan secretos (contadores, fechas, banderas). */
    private const NO_SENSIBLE = '/(tokens?_(input|output|total|in|out)$|^max_tokens$|_at$|_ready$|_status$|_message$|_existe$|^passed$|^passing_|_id$|_count$|_attempts$|_source$|_type$|^tokenable)/i';

    /** Tablas que solo guardan secretos, sesiones o colas internas: no se consultan. */
    private const TABLAS_BLOQUEADAS = '/^(sessions|password_resets?|password_reset_tokens|personal_access_tokens|oauth_\w+|jobs|job_batches|failed_jobs|cache|cache_locks|telescope_\w+|api_integrations|api_integration_\w+|ia_proveedores|email_settings|system_map_credentials|olt_credenciales|olt_smartolt_config|whatsapp_instances|\w*_credentials?|\w*credenciales\w*|portal_password_otps|parental_otp_codes|push_tokens|\w*_device_tokens|\w*_push_tokens)$/i';

    private const PALABRAS_PROHIBIDAS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'RENAME', 'GRANT', 'REVOKE',
        'LOCK', 'UNLOCK', 'CALL', 'HANDLER', 'LOAD', 'INTO', 'OUTFILE', 'DUMPFILE', 'PREPARE', 'EXECUTE', 'DEALLOCATE',
        'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK', 'RELEASE_ALL_LOCKS', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'LOAD_FILE',
        'KILL', 'SHUTDOWN', 'RESTART', 'CLONE', 'INSTALL', 'UNINSTALL', 'FLUSH', 'RESET', 'PURGE', 'CHANGE', 'SHOW',
        'DESCRIBE', 'EXPLAIN', 'TABLE', 'SET', 'DO', 'START', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'XA', 'USE', 'BINLOG',
        'SOURCE_POS_WAIT', 'MASTER_POS_WAIT', 'WAIT_FOR_EXECUTED_GTID_SET',
    ];

    private const ESQUEMAS_PROHIBIDOS = ['information_schema', 'mysql', 'performance_schema', 'sys'];

    public function __construct(private AdjuntosAsistente $adjuntos)
    {
    }

    /**
     * @param array<int, Adjunto> $archivos hojas que la consulta puede usar como tablas
     * @return array{columnas:array<int,string>, filas:array<int,array>, total:int, truncado:bool}
     */
    public function consultar(string $sql, array $archivos = [], int $maxFilas = 200, int $maxCaracteresCelda = 300, int $segundos = 20): array
    {
        $sql = $this->validar($sql, $archivos);
        [$prefijo, $bindings] = $this->ctesDeArchivos($archivos);
        $sql = $this->anteponerCtes($sql, $prefijo);

        $conexion = $this->conexion();
        try {
            $conexion->statement('SET SESSION TRANSACTION READ ONLY');
            $conexion->statement('SET SESSION max_execution_time = ' . max(1000, $segundos * 1000));

            $st = $conexion->getPdo()->prepare($sql);
            $st->execute($bindings);

            $columnas = [];
            for ($i = 0; $i < $st->columnCount(); $i++) {
                $columnas[] = (string) ($st->getColumnMeta($i)['name'] ?? "col{$i}");
            }
            $tapar = array_map(fn ($c) => self::esSensible($c), $columnas);

            $filas = [];
            $truncado = false;
            while (($fila = $st->fetch(PDO::FETCH_NUM)) !== false) {
                if (count($filas) >= $maxFilas) {
                    $truncado = true;
                    break;
                }
                foreach ($fila as $i => $v) {
                    $fila[$i] = $tapar[$i] ? ($v === null ? null : '••••') : $this->limpiarValor($v, $maxCaracteresCelda);
                }
                $filas[] = $fila;
            }
            $st->closeCursor();

            return ['columnas' => $columnas, 'filas' => $filas, 'total' => count($filas), 'truncado' => $truncado];
        } catch (\PDOException $e) {
            throw new ConsultaRechazada($this->explicarError($e), 0, $e);
        } finally {
            DB::purge(self::CONEXION);
        }
    }

    // ── Revisión de la consulta ───────────────────────────────────────────────────────────────

    public function validar(string $sql, array $archivos = []): string
    {
        $sql = trim(preg_replace('/;\s*$/', '', trim($sql)));
        if ($sql === '') {
            throw new ConsultaRechazada('La consulta está vacía.');
        }
        if (mb_strlen($sql) > 20000) {
            throw new ConsultaRechazada('La consulta es demasiado larga.');
        }

        $tokens = $this->tokens($sql);
        $palabras = array_values(array_filter($tokens, fn ($t) => $t['t'] === 'palabra'));
        $primera = strtoupper($palabras[0]['v'] ?? '');
        if (!in_array($primera, ['SELECT', 'WITH'], true)) {
            throw new ConsultaRechazada('Solo puedo hacer consultas de lectura (SELECT).');
        }

        $tablas = $this->tablas();
        $nombresArchivo = [];
        foreach ($archivos as $a) {
            foreach ($this->adjuntos->tablasDeHoja($a) as $t) {
                $nombresArchivo[$t] = true;
            }
        }
        $otrasBases = $this->otrasBases();
        $usaSensible = false;

        foreach ($tokens as $i => $tok) {
            if ($tok['t'] === 'simbolo') {
                if ($tok['v'] === ';') {
                    throw new ConsultaRechazada('Solo puedo correr una consulta a la vez.');
                }
                if (in_array($tok['v'], [':=', '@'], true)) {
                    throw new ConsultaRechazada('No uso variables en las consultas.');
                }
                continue;
            }
            if ($tok['t'] === 'texto') {
                if (preg_match('/(pass(word|wd)|contrase|secret|token|api_?key|apikey|credential|private_?key)/i', $tok['v'])) {
                    throw new ConsultaRechazada('Esa información es confidencial (contraseñas, llaves o claves de acceso) y no la consulto.');
                }
                continue;
            }
            if ($tok['t'] === 'numero') {
                continue;
            }

            $v = $tok['v'];
            $min = strtolower($v);
            if ($tok['t'] === 'palabra' && in_array(strtoupper($v), self::PALABRAS_PROHIBIDAS, true)) {
                // «CHARACTER SET» y «LOCK IN SHARE MODE» no entran: SET y LOCK quedan fuera igual.
                throw new ConsultaRechazada('Solo puedo leer información; esa consulta lleva una instrucción que no se permite (' . strtoupper($v) . ').');
            }
            if (in_array($min, self::ESQUEMAS_PROHIBIDOS, true) || isset($otrasBases[$min])) {
                throw new ConsultaRechazada('Solo consulto la información de este sistema. Para saber qué tablas y columnas hay, usa explorar_datos.');
            }
            if (isset($tablas[$min])) {
                if (preg_match(self::TABLAS_BLOQUEADAS, $min)) {
                    throw new ConsultaRechazada('Esa información es interna del sistema (sesiones, llaves o colas) y no la consulto.');
                }
                if (!empty($tablas[$min]['sensibles'])) {
                    $usaSensible = true;
                }
                continue;
            }
            if (isset($nombresArchivo[$min])) {
                continue;
            }
            if (self::esSensible($v)) {
                throw new ConsultaRechazada('Esa información es confidencial (contraseñas, llaves o claves de acceso) y no la consulto.');
            }
        }

        // Con «*» sobre una tabla con secretos, el resultado conserva el nombre de cada columna y ahí se
        // tapan. Lo único que lo evitaría es renombrarlas sin nombrarlas (UNION, o listas de columnas
        // en WITH o en una subconsulta): esa combinación no se permite.
        if ($usaSensible && $this->usaAsterisco($tokens) && $this->renombraColumnas($tokens)) {
            throw new ConsultaRechazada('Esa tabla guarda datos confidenciales: nombra las columnas que necesitas en lugar de usar *.');
        }

        return $sql;
    }

    public static function esSensible(string $nombre): bool
    {
        return (bool) preg_match(self::SENSIBLE, $nombre) && !preg_match(self::NO_SENSIBLE, $nombre);
    }

    /** «*» de selección (SELECT *, t.*, DISTINCT *), no el de COUNT(*) ni el de multiplicar. */
    private function usaAsterisco(array $tokens): bool
    {
        $previo = null;
        foreach ($tokens as $tok) {
            if ($tok['t'] === 'simbolo' && $tok['v'] === '*' && $previo !== null) {
                $p = strtoupper($previo['v']);
                if (in_array($p, ['SELECT', 'DISTINCT', 'ALL', ',', '.', 'DISTINCTROW'], true)) {
                    return true;
                }
            }
            $previo = $tok;
        }

        return false;
    }

    /** UNION/INTERSECT/EXCEPT, «nombre (cols) AS (» de WITH o «(subconsulta) alias (cols)». */
    private function renombraColumnas(array $tokens): bool
    {
        $clave = ['IN', 'OVER', 'AND', 'OR', 'NOT', 'EXISTS', 'ANY', 'ALL', 'SOME', 'ON', 'USING', 'VALUES', 'WHEN', 'THEN', 'ELSE', 'AS', 'WHERE', 'HAVING', 'XOR', 'IS', 'LIKE', 'BETWEEN'];
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $v = strtoupper($tokens[$i]['v']);
            if ($tokens[$i]['t'] === 'palabra' && in_array($v, ['UNION', 'INTERSECT', 'EXCEPT'], true)) {
                return true;
            }
            if ($tokens[$i]['v'] !== ')' || $i + 2 >= $n) {
                continue;
            }
            $a = $tokens[$i + 1];
            $b = $tokens[$i + 2];
            if (strtoupper($a['v']) === 'AS' && $b['v'] === '(') {
                return true;
            }
            if (in_array($a['t'], ['palabra', 'identificador'], true) && !in_array(strtoupper($a['v']), $clave, true) && $b['v'] === '(') {
                return true;
            }
            if (strtoupper($a['v']) === 'AS' && $i + 3 < $n && in_array($b['t'], ['palabra', 'identificador'], true) && $tokens[$i + 3]['v'] === '(') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{t:string, v:string}> t = palabra | identificador | texto | numero | simbolo
     */
    private function tokens(string $sql): array
    {
        $tokens = [];
        $n = strlen($sql);
        $i = 0;
        while ($i < $n) {
            $c = $sql[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            $dos = substr($sql, $i, 2);
            if ($dos === '/*' || $dos === '--' || $c === '#') {
                throw new ConsultaRechazada('La consulta no puede llevar comentarios.');
            }
            if ($c === "'" || $c === '"') {
                $j = $i + 1;
                $valor = '';
                while ($j < $n) {
                    if ($sql[$j] === '\\' && $j + 1 < $n) {
                        $valor .= $sql[$j + 1];
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $c) {
                        if ($j + 1 < $n && $sql[$j + 1] === $c) {
                            $valor .= $c;
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $valor .= $sql[$j++];
                }
                if ($j >= $n) {
                    throw new ConsultaRechazada('La consulta tiene un texto sin cerrar.');
                }
                $tokens[] = ['t' => 'texto', 'v' => $valor];
                $i = $j + 1;
                continue;
            }
            if ($c === '`') {
                $j = strpos($sql, '`', $i + 1);
                if ($j === false) {
                    throw new ConsultaRechazada('La consulta tiene un nombre sin cerrar.');
                }
                $tokens[] = ['t' => 'identificador', 'v' => substr($sql, $i + 1, $j - $i - 1)];
                $i = $j + 1;
                continue;
            }
            if (ctype_digit($c)) {
                preg_match('/\G[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?/', $sql, $m, 0, $i);
                $tokens[] = ['t' => 'numero', 'v' => $m[0]];
                $i += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G[A-Za-z_\x80-\xFF$][A-Za-z0-9_\x80-\xFF$]*/', $sql, $m, 0, $i)) {
                $tokens[] = ['t' => 'palabra', 'v' => $m[0]];
                $i += strlen($m[0]);
                continue;
            }
            foreach ([':=', '->>', '->', '<=>', '<=', '>=', '<>', '!=', '||', '&&', '<<', '>>'] as $op) {
                if (substr($sql, $i, strlen($op)) === $op) {
                    $tokens[] = ['t' => 'simbolo', 'v' => $op];
                    $i += strlen($op);
                    continue 2;
                }
            }
            $tokens[] = ['t' => 'simbolo', 'v' => $c];
            $i++;
        }

        return $tokens;
    }

    // ── Esquema ───────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{filas:int, comentario:string, sensibles:array<int,string>}> */
    public function tablas(): array
    {
        return Cache::remember('asistente:esquema:tablas', now()->addMinutes(10), function () {
            $base = DB::getDatabaseName();
            $tablas = [];
            foreach (DB::select('SELECT TABLE_NAME n, TABLE_ROWS f, TABLE_COMMENT c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$base]) as $t) {
                $tablas[strtolower($t->n)] = ['nombre' => $t->n, 'filas' => (int) $t->f, 'comentario' => (string) $t->c, 'sensibles' => []];
            }
            foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?', [$base]) as $col) {
                if (self::esSensible($col->c) && isset($tablas[strtolower($col->t)])) {
                    $tablas[strtolower($col->t)]['sensibles'][] = $col->c;
                }
            }

            return $tablas;
        });
    }

    public function tablaBloqueada(string $tabla): bool
    {
        return (bool) preg_match(self::TABLAS_BLOQUEADAS, strtolower($tabla));
    }

    /** @return array<string, true> */
    private function otrasBases(): array
    {
        return Cache::remember('asistente:esquema:bases', now()->addMinutes(10), function () {
            $actual = strtolower(DB::getDatabaseName());
            $otras = [];
            foreach (DB::select('SELECT SCHEMA_NAME n FROM information_schema.SCHEMATA') as $s) {
                if (strtolower($s->n) !== $actual) {
                    $otras[strtolower($s->n)] = true;
                }
            }

            return $otras;
        });
    }

    // ── Hojas de Excel como tablas ────────────────────────────────────────────────────────────

    /** @return array{0:string, 1:array} definición WITH de cada hoja y sus datos (JSON) para enlazar */
    private function ctesDeArchivos(array $archivos): array
    {
        $ctes = [];
        $bindings = [];
        $colacion = DB::selectOne('SELECT @@collation_database c')->c ?? 'utf8mb4_unicode_ci';

        foreach ($archivos as $a) {
            foreach ((array) $a->meta('hojas', []) as $h) {
                $datos = $this->adjuntos->hoja($a, (int) $h['n']);
                if (!$datos) {
                    throw new ConsultaRechazada("El archivo #{$a->id} ya no está disponible.");
                }
                $columnas = ['`_fila` FOR ORDINALITY'];
                foreach ($datos['columnas'] as $i => $col) {
                    $tipo = match ($col['tipo']) {
                        'numero' => 'DOUBLE',
                        'fecha'  => $this->conHora(array_column($datos['filas'], $i)) ? 'DATETIME' : 'DATE',
                        default  => "VARCHAR(2000) COLLATE {$colacion}",
                    };
                    $columnas[] = '`' . str_replace('`', '', $col['id']) . "` {$tipo} PATH '\$[{$i}]' NULL ON EMPTY NULL ON ERROR";
                }
                $nombre = AdjuntosAsistente::nombreTabla($a->id, (int) $h['n']);
                $ctes[] = "`{$nombre}` AS (SELECT * FROM JSON_TABLE(?, '\$[*]' COLUMNS (" . implode(', ', $columnas) . ')) AS jt)';
                $bindings[] = json_encode($datos['filas'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }

        return [implode(', ', $ctes), $bindings];
    }

    private function conHora(array $valores): bool
    {
        foreach ($valores as $v) {
            if (is_string($v) && strlen($v) > 10) {
                return true;
            }
        }

        return false;
    }

    private function anteponerCtes(string $sql, string $ctes): string
    {
        if ($ctes === '') {
            return $sql;
        }
        if (preg_match('/^\s*WITH\s+(RECURSIVE\s+)?/i', $sql, $m)) {
            return 'WITH ' . ($m[1] ?? '') . $ctes . ', ' . substr($sql, strlen($m[0]));
        }

        return 'WITH ' . $ctes . ' ' . $sql;
    }

    // ── Conexión y resultados ─────────────────────────────────────────────────────────────────

    private function conexion(): Connection
    {
        $base = config('database.connections.' . config('database.default'));
        $base['options'] = ($base['options'] ?? []) + [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false];
        config(['database.connections.' . self::CONEXION => $base]);

        return DB::connection(self::CONEXION);
    }

    private function limpiarValor(mixed $v, int $max): mixed
    {
        if (!is_string($v)) {
            return $v;
        }
        // JSON guardado en una columna (configuraciones, bitácoras): se tapan sus llaves secretas.
        $t = ltrim($v);
        if ($t !== '' && ($t[0] === '{' || $t[0] === '[') && is_array($json = json_decode($v, true))) {
            $v = json_encode($this->taparJson($json), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!mb_check_encoding($v, 'UTF-8')) {
            return '(dato binario)';
        }

        return $max > 0 && mb_strlen($v) > $max ? mb_substr($v, 0, $max) . '…' : $v;
    }

    private function taparJson(array $datos): array
    {
        foreach ($datos as $k => $v) {
            if (is_string($k) && self::esSensible($k)) {
                $datos[$k] = $v === null || $v === '' ? $v : '••••';
            } elseif (is_array($v)) {
                $datos[$k] = $this->taparJson($v);
            }
        }

        return $datos;
    }

    private function explicarError(\PDOException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '3024') || stripos($msg, 'maximum statement execution time') !== false) {
            return 'La consulta tardó demasiado. Hazla más concreta (filtra por fechas, usa LIMIT o agrupa).';
        }
        if (str_contains($msg, '1792')) {
            return 'Solo puedo leer información, no cambiarla.';
        }
        // El error de MySQL sí se le da a la IA (sin la consulta completa) para que corrija su consulta.
        $msg = preg_replace('/\s*\(Connection:.*$/s', '', $msg);

        return 'La consulta tiene un error: ' . mb_substr(preg_replace('/^SQLSTATE\[\w+\]:\s*/', '', $msg), 0, 400);
    }
}
