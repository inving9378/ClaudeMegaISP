<?php

namespace App\Services\Asistente\Sistema;

use App\Http\Middleware\SoloLectura;
use App\Models\User;
use App\Services\Asistente\ConocimientoAsistente;
use App\Services\Asistente\PermisosAsistente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Las funciones del sistema que JARVIS puede usar: las mismas rutas que usan las pantallas del
 * personal (grupo web + sesión). Quedan fuera el portal de clientes, la API con token y lo que
 * limites.json → rutas_bloqueadas marca (salir de la sesión, instalar módulos, publicar versiones…).
 *
 * Cada función es «consulta» o «cambio» con la misma regla del rol «Solo lectura»
 * (SoloLectura::esLectura): las consultas JARVIS las corre directo; los cambios esperan el «sí».
 */
class CatalogoFunciones
{
    /** Middleware que marca una ruta que NO es del personal (portal, app con token, flotas de clientes). */
    private const AJENAS = '/(AuthPortal|PortalImpersonacion|Authenticate:sanctum|FlotasPortalContext|LogApiMobileAccess|IdempotenciaAppMiddleware|CuentaActivaMiddleware)/';

    private const SINONIMOS = [
        'cliente' => ['client', 'customer'], 'pago' => ['payment', 'pay'], 'factura' => ['invoice', 'billing', 'document'],
        'ticket' => ['ticket', 'task'], 'tarea' => ['task', 'scheduling'], 'plan' => ['plan', 'package'],
        'colonia' => ['colon'], 'vendedor' => ['seller', 'vendedor'], 'servicio' => ['service'], 'red' => ['network', 'red'],
        'inventario' => ['inventory'], 'usuario' => ['user'], 'equipo' => ['equipment', 'device', 'inventory'],
        'prospecto' => ['crm', 'lead', 'prospect'], 'gasto' => ['expense', 'gasto'], 'reporte' => ['report'],
        'crear' => ['store', 'create', 'add'], 'editar' => ['update', 'edit'], 'borrar' => ['destroy', 'delete'],
        'eliminar' => ['destroy', 'delete'], 'listar' => ['table', 'list', 'index'], 'buscar' => ['search', 'find'],
        'suspender' => ['suspend', 'cut', 'corte'], 'activar' => ['activ', 'enable'], 'promocion' => ['promo'],
        'caja' => ['caja', 'box', 'nap'], 'empleado' => ['employee', 'colaborador', 'talento'], 'vehiculo' => ['fleet', 'flota', 'vehicle'],
        'llamada' => ['call', 'voip', 'cdr'], 'campana' => ['campaign', 'campana'], 'nota' => ['note', 'comment'],
    ];

    /** @var array<int, array>|null */
    private static ?array $rutas = null;

    public function __construct(private Router $router, private ConocimientoAsistente $conocimiento)
    {
    }

    /**
     * Armarlo resuelve el middleware de ~3,500 rutas (instancia cada controlador): segundos. Se guarda
     * en caché con una huella de las rutas y de rutas_bloqueadas, así que se rehace solo cuando cambian.
     *
     * @return array<int, array{metodo:string, uri:string, nombre:?string, accion:string, controlador:?string, metodo_controlador:?string, middleware:array, tipo:string}>
     */
    public function rutas(): array
    {
        if (self::$rutas !== null) {
            return self::$rutas;
        }

        $bloqueadas = (array) $this->conocimiento->ajuste('rutas_bloqueadas', []);
        $huella = hash_init('md5');
        foreach ($this->router->getRoutes()->getRoutes() as $ruta) {
            hash_update($huella, implode('|', $ruta->methods()) . $ruta->uri() . json_encode($ruta->getAction('uses')) . json_encode($ruta->getAction('middleware')) . "\n");
        }
        hash_update($huella, json_encode($bloqueadas) . 'v1');

        return self::$rutas = \Illuminate\Support\Facades\Cache::remember('asistente:catalogo:' . hash_final($huella), now()->addDays(7), fn () => $this->armar($bloqueadas));
    }

    private function armar(array $bloqueadas): array
    {
        $rutas = [];
        foreach ($this->router->getRoutes()->getRoutes() as $ruta) {
            /** @var Route $ruta */
            $mw = $this->router->gatherRouteMiddleware($ruta);
            $texto = implode(' ', array_map(fn ($m) => is_string($m) ? $m : 'Closure', $mw));
            if (!in_array('web', $ruta->gatherMiddleware(), true) || !str_contains($texto, 'Authenticate') || preg_match(self::AJENAS, $texto)) {
                continue;
            }
            $uri = '/' . ltrim($ruta->uri(), '/');
            if ($this->bloqueada($uri, $bloqueadas)) {
                continue;
            }
            $uses = $ruta->getAction('uses');
            $controlador = is_string($uses) ? Str::before($uses, '@') : null;
            $metodoCtrl = is_string($uses) ? $ruta->getActionMethod() : null;

            foreach (array_diff($ruta->methods(), ['HEAD']) as $verbo) {
                $rutas[] = [
                    'metodo'             => $verbo,
                    'uri'                => $uri,
                    'nombre'             => $ruta->getName(),
                    'accion'             => $controlador ? class_basename($controlador) . '@' . $metodoCtrl : 'closure',
                    'controlador'        => $controlador,
                    'metodo_controlador' => $metodoCtrl,
                    'middleware'         => array_values(array_filter($mw, 'is_string')),
                    'tipo'               => SoloLectura::esLectura($verbo, $metodoCtrl) ? 'consulta' : 'cambio',
                ];
            }
        }

        return $rutas;
    }

    public function bloqueada(string $uri, ?array $patrones = null): bool
    {
        foreach ($patrones ?? (array) $this->conocimiento->ajuste('rutas_bloqueadas', []) as $p) {
            if (@preg_match('#' . str_replace('#', '\#', $p) . '#i', $uri)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array> las que mejor coinciden con el texto y el usuario puede usar */
    public function buscar(User $user, string $texto, int $max = 25): array
    {
        $palabras = $this->palabras($texto);
        if (!$palabras) {
            return [];
        }

        $rutas = $this->rutas();
        $textos = array_map(fn ($r) => strtolower($r['uri'] . ' ' . $r['nombre'] . ' ' . $r['accion']), $rutas);
        $total = count($rutas);
        $peso = [];
        foreach ($palabras as $grupo) {
            foreach ($grupo as $p) {
                $df = 0;
                foreach ($textos as $t) {
                    $df += str_contains($t, $p) ? 1 : 0;
                }
                $peso[$p] = log(($total + 1) / ($df + 1)) + 0.5;
            }
        }

        $resultados = [];
        foreach ($rutas as $i => $r) {
            $uri = strtolower($r['uri']);
            $nombre = strtolower((string) $r['nombre']);
            $accion = strtolower($r['accion']);
            $puntos = 0;
            $grupos = 0;
            foreach ($palabras as $grupo) {
                $hit = 0;
                foreach ($grupo as $p) {
                    if (!str_contains($textos[$i], $p)) {
                        continue;
                    }
                    // El mejor lugar donde aparece, y un poco más si se repite (el nombre suele copiar la ruta).
                    $lugares = array_filter([str_contains($uri, $p) ? 3 : 0, str_contains($accion, $p) ? 2.5 : 0, str_contains($nombre, $p) ? 2 : 0]);
                    $lugar = max($lugares ?: [0]) + 0.5 * (count($lugares) - 1);
                    $hit = max($hit, $lugar * $peso[$p]);
                }
                $puntos += $hit;
                $grupos += $hit > 0 ? 1 : 0;
            }
            if ($puntos > 0) {
                $resultados[] = ['r' => $r, 'p' => $puntos * (1 + $grupos) - substr_count($uri, '/') * 0.1];
            }
        }
        usort($resultados, fn ($a, $b) => $b['p'] <=> $a['p']);

        $salida = [];
        foreach ($resultados as $x) {
            if ($this->puedeUsar($user, $x['r'])) {
                $salida[] = $this->paraIa($x['r']);
            }
            if (count($salida) >= $max) {
                break;
            }
        }

        return $salida;
    }

    /** La función que atiende esta petición, si es del catálogo. */
    public function encontrar(string $metodo, string $ruta): ?array
    {
        $ruta = '/' . ltrim(parse_url($ruta, PHP_URL_PATH) ?: '', '/');
        try {
            $match = $this->router->getRoutes()->match(Request::create($ruta, strtoupper($metodo)));
        } catch (\Throwable) {
            return null;
        }
        $uri = '/' . ltrim($match->uri(), '/');
        foreach ($this->rutas() as $r) {
            if ($r['uri'] === $uri && $r['metodo'] === strtoupper($metodo)) {
                return $r;
            }
        }

        return null;
    }

    /** Lo que pide una función: parámetros de la ruta, reglas de validación y el código del método. */
    public function detalle(array $r): array
    {
        $d = $this->paraIa($r) + ['parametros_en_ruta' => $this->parametrosDeRuta($r['uri'])];
        if (!$r['controlador'] || !method_exists($r['controlador'], (string) $r['metodo_controlador'])) {
            return $d + ['nota' => 'Es una función sin controlador; no hay más detalle.'];
        }

        try {
            $ref = new \ReflectionMethod($r['controlador'], $r['metodo_controlador']);
            foreach ($ref->getParameters() as $param) {
                $tipo = $param->getType();
                $clase = $tipo instanceof \ReflectionNamedType && !$tipo->isBuiltin() ? $tipo->getName() : null;
                if ($clase && is_subclass_of($clase, FormRequest::class)) {
                    $d['validacion'] = $this->reglasDe($clase);
                }
            }
            $lineas = file($ref->getFileName());
            $codigo = implode('', array_slice($lineas, $ref->getStartLine() - 1, min(140, $ref->getEndLine() - $ref->getStartLine() + 1)));
            $d['codigo'] = $codigo;
        } catch (\Throwable) {
            // Sin código: la IA trabaja con la ruta y los parámetros.
        }

        return $d;
    }

    public function puedeUsar(User $user, array $r): bool
    {
        if ($user->isAdmin() || $user->isDevelopment() || $user->isSuperAdmin()) {
            return true;
        }

        foreach ($r['middleware'] as $m) {
            [$clase, $params] = array_pad(explode(':', $m, 2), 2, '');
            $lista = $params === '' ? [] : preg_split('/[|,]/', $params);
            if (str_ends_with($clase, 'PermissionMiddleware') && !PermisosAsistente::puede($user, $lista)) {
                return false;
            }
            if (str_ends_with($clase, 'RoleMiddleware') && !$user->hasAnyRole($lista)) {
                return false;
            }
            if (str_ends_with($clase, 'Authorize') && $lista && !$user->can($lista[0])) {
                return false;
            }
            if (str_ends_with($clase, 'CheckRoutePermission') && !$this->permisoDeRuta($user, $r['uri'])) {
                return false;
            }
        }

        return true;
    }

    /** La misma búsqueda de CheckRoutePermission: algún permiso del usuario cubre la ruta. */
    private function permisoDeRuta(User $user, string $uri): bool
    {
        $ejemplo = preg_replace('/\{[^}]+\}/', 'x', $uri);
        foreach ((array) config('route_permission') as $permiso => $patrones) {
            foreach ((array) $patrones as $patron) {
                $regex = '#^' . preg_replace('/\{[^\}]+\}/', '[^/]+', str_replace('**', '.+', $patron)) . '$#';
                if (@preg_match($regex, $ejemplo) && PermisosAsistente::puede($user, $permiso)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function paraIa(array $r): array
    {
        return [
            'metodo' => $r['metodo'],
            'ruta'   => $r['uri'],
            'nombre' => $r['nombre'],
            'accion' => $r['accion'],
            'tipo'   => $r['tipo'],
        ];
    }

    private function parametrosDeRuta(string $uri): array
    {
        preg_match_all('/\{(\w+)\??\}/', $uri, $m);

        return $m[1];
    }

    private function reglasDe(string $clase): ?array
    {
        try {
            $req = $clase::createFrom(request());
            $req->setContainer(app())->setRedirector(app('redirect'));
            $reglas = $req->rules();

            return array_map(fn ($r) => is_array($r) ? implode('|', array_map(fn ($x) => is_string($x) ? $x : class_basename($x), $r)) : (is_string($r) ? $r : class_basename($r)), $reglas);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, array<int, string>> una lista de variantes por palabra buscada */
    private function palabras(string $texto): array
    {
        $grupos = [];
        foreach (preg_split('/[\s,;\/]+/', Str::lower(Str::ascii($texto))) as $p) {
            $p = trim($p, "-_. \t");
            if (mb_strlen($p) < 3 || in_array($p, ['para', 'con', 'los', 'las', 'del', 'que', 'una', 'uno', 'por'], true)) {
                continue;
            }
            $raiz = preg_replace('/(es|s)$/', '', $p);
            $variantes = array_unique(array_merge([$p, $raiz], self::SINONIMOS[$p] ?? [], self::SINONIMOS[$raiz] ?? []));
            $grupos[] = array_values(array_filter($variantes, fn ($v) => strlen($v) >= 3));
        }

        return $grupos;
    }
}
