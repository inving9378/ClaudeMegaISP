<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * WAF ligero contra inyección SQL, aplicado globalmente a todas las rutas.
 *
 * Escanea los valores del request (body, query y parámetros de ruta) en busca de
 * firmas de inyección de ALTA PRECISIÓN: UNION SELECT, queries apiladas
 * destructivas, tautologías (' OR 1=1), funciones time-based (SLEEP/BENCHMARK),
 * acceso a archivos (LOAD_FILE/INTO OUTFILE), information_schema, xp_cmdshell, etc.
 *
 * Diseñado para FALSOS POSITIVOS MÍNIMOS: solo bloquea patrones que casi nunca
 * aparecen en datos legítimos de formularios. Configurable en config/sql_guard.php:
 *   - SQL_GUARD_ENABLED=false → desactiva por completo.
 *   - SQL_GUARD_BLOCK=false   → modo observación (registra, no bloquea).
 *
 * Defensa en profundidad. La defensa primaria contra inyección por NOMBRE de
 * columna/modelo/scope está en App\Support\Security\QueryGuard, aplicada en los
 * controladores que arman queries dinámicas (p.ej. SearchModelController).
 */
class SqlInjectionProtection
{
    /** Firmas de inyección. Cada una debe ser improbable en datos legítimos. */
    private const PATTERNS = [
        // UNION-based
        '/\bunion\b[\s\/*]+(all[\s\/*]+)?\bselect\b/i',
        // Queries apiladas destructivas tras `;`
        '/;\s*\b(drop|alter|truncate|create|grant|insert|update|delete|replace|rename)\b/i',
        // Inyección time-based (blind)
        '/\b(sleep|benchmark|pg_sleep|dbms_pipe\.receive_message)\s*\(/i',
        '/\bwaitfor\s+delay\b/i',
        // Acceso a archivos del servidor
        '/\bload_file\s*\(/i',
        '/\binto\s+(out|dump)file\b/i',
        // Tautologías clásicas: ' or 'a'='a , ') or (1=1
        '/[\'"`)]\s*(or|and)\s+[\'"\d(]?[\w.]+\s*(=|<>|!=|<|>|\blike\b)\s*[\'"\d(]?[\w.]+/i',
        '/\b(or|and)\b\s+\d+\s*=\s*\d+/i',
        // Cierre de cadena seguido de comentario SQL ( ' -- )
        '/[\'"]\s*--/',
        // Tablas/catálogos de sistema y ejecución de comandos
        '/\b(information_schema|sysobjects|sys\.databases|pg_catalog|xp_cmdshell)\b/i',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('sql_guard.enabled', true)) {
            return $next($request);
        }

        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        $exceptFields = array_map('strtolower', config('sql_guard.except_fields', []));

        $hit = $this->scan($request->all(), $exceptFields);
        if ($hit === null && $request->route()) {
            $hit = $this->scan($request->route()->parameters(), $exceptFields);
        }

        if ($hit !== null) {
            Log::channel(config('sql_guard.log_channel', 'stack'))->warning(
                '[SQLGuard] Posible inyección SQL detectada',
                [
                    'route'  => $request->path(),
                    'method' => $request->method(),
                    'ip'     => $request->ip(),
                    'user'   => optional($request->user())->id,
                    'field'  => $hit['field'],
                    'sample' => mb_substr($hit['value'], 0, 200),
                ]
            );

            if (config('sql_guard.block', true)) {
                abort(403, 'Solicitud bloqueada por seguridad.');
            }
        }

        return $next($request);
    }

    private function isExcludedRoute(Request $request): bool
    {
        foreach ((array) config('sql_guard.except_routes', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recorre recursivamente el input y devuelve el primer match.
     *
     * @return array{field:string,value:string}|null
     */
    private function scan($data, array $exceptFields, string $parentKey = ''): ?array
    {
        foreach ((array) $data as $key => $value) {
            $fieldName = is_string($key) ? strtolower($key) : '';
            if ($fieldName !== '' && in_array($fieldName, $exceptFields, true)) {
                continue;
            }

            $path = $parentKey !== '' ? "$parentKey.$key" : (string) $key;

            if (is_array($value)) {
                $found = $this->scan($value, $exceptFields, $path);
                if ($found !== null) {
                    return $found;
                }
                continue;
            }

            if (!is_string($value) || $value === '') {
                continue;
            }

            if ($this->isMalicious($value)) {
                return ['field' => $path, 'value' => $value];
            }
        }
        return null;
    }

    private function isMalicious(string $value): bool
    {
        // Escanea el valor crudo y su versión url-decodificada (evita evasión
        // por percent-encoding en payloads que Laravel no decodifica solo).
        $candidates = [$value];
        $decoded = urldecode($value);
        if ($decoded !== $value) {
            $candidates[] = $decoded;
        }

        foreach ($candidates as $candidate) {
            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, $candidate)) {
                    return true;
                }
            }
        }
        return false;
    }
}
