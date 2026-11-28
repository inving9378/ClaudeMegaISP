<?php

namespace App\Services\Asistente\Sistema;

use App\Models\Asistente\Conversacion;
use App\Models\User;
use App\Services\Asistente\AdjuntosAsistente;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Usa una función del sistema como si el usuario hiciera clic: la petición pasa por la MISMA ruta,
 * con sus permisos (CheckRoutePermission, Spatie, Solo lectura), su validación y su controlador.
 * JARVIS nunca puede más que el usuario con su sesión.
 *
 * Diferencias con una petición del navegador, a propósito:
 *  - no se reinicia la sesión ni se tocan las cookies (es la sesión del propio usuario, ya abierta);
 *  - no se pide el token CSRF (la petición nace en el servidor, a partir de un «sí» que sí lo trajo);
 *  - no cuenta como «última pantalla visitada»;
 *  - los avisos que la pantalla dejaría para la siguiente página se recogen aquí y no se muestran.
 */
class DespachadorInterno
{
    private const SIN_ESTOS = [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \App\Http\Middleware\TrackLastVisitedRoute::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\RegistroRendimiento::class,
    ];

    private const AVISOS = ['success', 'error', 'message', 'status', 'warning', 'info', 'mensaje', 'exito', 'alert'];

    public function __construct(private Router $router, private AdjuntosAsistente $adjuntos)
    {
    }

    /**
     * @return array{estado:int, ok:bool, tipo:string, cuerpo:mixed, avisos?:array, enlace?:string}
     */
    public function ejecutar(User $user, string $metodo, string $ruta, array $datos = [], ?Conversacion $conversacion = null): array
    {
        $app = app();
        $original = $app->bound('request') ? $app->make('request') : Request::capture();
        $sub = $this->peticion($original, strtoupper($metodo), $ruta, $datos);

        $sesion = $original->hasSession() ? $original->session() : $app->make('session')->driver('array');
        $sub->setLaravelSession($sesion);
        $usuarioAntes = Auth::guard('web')->user();
        if (!$usuarioAntes || (int) $usuarioAntes->getAuthIdentifier() !== (int) $user->id) {
            Auth::guard('web')->setUser($user);
        }
        $sub->setUserResolver(fn ($guard = null) => Auth::guard($guard)->user());

        $flashAntes = (array) $sesion->get('_flash.new', []);
        $actualAntes = $this->rutaActual();

        $app->instance('request', $sub);
        Facade::clearResolvedInstance('request');
        $app['url']->setRequest($sub);

        try {
            $respuesta = $this->correr($app, $sub);
        } finally {
            $app->instance('request', $original);
            Facade::clearResolvedInstance('request');
            $app['url']->setRequest($original);
            $this->fijarRutaActual(...$actualAntes);
            if ($usuarioAntes && (int) $usuarioAntes->getAuthIdentifier() !== (int) $user->id) {
                Auth::guard('web')->setUser($usuarioAntes);
            }
        }

        $avisos = $this->recogerAvisos($sesion, $flashAntes);

        return $this->interpretar($respuesta, $avisos, $user, $conversacion);
    }

    private function peticion(Request $original, string $metodo, string $ruta, array $datos): Request
    {
        $ruta = '/' . ltrim($ruta, '/');
        $server = array_filter([
            'REMOTE_ADDR'     => $original->server('REMOTE_ADDR', '127.0.0.1'),
            'HTTP_HOST'       => $original->server('HTTP_HOST'),
            'SERVER_NAME'     => $original->server('SERVER_NAME'),
            'SERVER_PORT'     => $original->server('SERVER_PORT'),
            'HTTPS'           => $original->server('HTTPS'),
            'HTTP_USER_AGENT' => 'JARVIS (asistente de MegaISP)',
            'HTTP_ACCEPT'     => 'application/json, text/plain, */*',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_REFERER'    => $original->headers->get('referer'),
        ], fn ($v) => $v !== null);

        $sub = Request::create($ruta, $metodo, $datos, [], [], $server);
        // Que el controlador lea igual con input(), all() o json().
        if (!in_array($metodo, ['GET', 'HEAD'], true)) {
            $sub->setJson(new \Symfony\Component\HttpFoundation\InputBag($datos));
        }

        return $sub;
    }

    private function correr($app, Request $sub): Response
    {
        try {
            $ruta = $this->router->getRoutes()->match($sub);
            $ruta->setContainer($app);
            $sub->setRouteResolver(fn () => $ruta);
            $this->fijarRutaActual($ruta, $sub);

            $kernel = $app->make(HttpKernel::class);
            $global = array_values(array_diff(\Closure::bind(fn () => $this->middleware, $kernel, get_class($kernel))(), self::SIN_ESTOS));
            $deRuta = array_values(array_filter($this->router->gatherRouteMiddleware($ruta), function ($m) {
                return !is_string($m) || !in_array(Str::before($m, ':'), self::SIN_ESTOS, true);
            }));

            return (new Pipeline($app))
                ->send($sub)
                ->through(array_merge($global, $deRuta))
                ->then(fn ($req) => $this->router->prepareResponse($req, $ruta->run()));
        } catch (\Throwable $e) {
            $manejador = $app->make(ExceptionHandler::class);
            if ($manejador->shouldReport($e)) {
                $manejador->report($e);
            }

            return $this->router->prepareResponse($sub, $manejador->render($sub, $e));
        }
    }

    private function interpretar(Response $r, array $avisos, User $user, ?Conversacion $conversacion): array
    {
        $estado = $r->getStatusCode();
        $base = ['estado' => $estado, 'ok' => $estado < 400] + ($avisos ? ['avisos' => $avisos] : []);

        if ($r instanceof JsonResponse) {
            $cuerpo = $r->getData(true);
            // Con APP_DEBUG, un error trae archivo, línea y traza: a la IA solo le sirve el mensaje.
            if (is_array($cuerpo) && isset($cuerpo['exception'])) {
                unset($cuerpo['exception'], $cuerpo['file'], $cuerpo['line'], $cuerpo['trace']);
            }
            if (is_array($cuerpo) && (($cuerpo['success'] ?? null) === false || ($cuerpo['ok'] ?? null) === false)) {
                $base['ok'] = false;
            }

            return $base + ['tipo' => 'json', 'cuerpo' => $cuerpo];
        }
        if ($r instanceof RedirectResponse) {
            if (isset($avisos['error']) || isset($avisos['errors'])) {
                $base['ok'] = false;
            }

            return $base + ['tipo' => 'redireccion', 'cuerpo' => $this->rutaInterna($r->getTargetUrl())];
        }

        $disposicion = (string) $r->headers->get('Content-Disposition');
        if ($r instanceof BinaryFileResponse || $r instanceof StreamedResponse || str_contains(strtolower($disposicion), 'attachment')) {
            return $base + $this->guardarArchivo($r, $disposicion, $user, $conversacion);
        }

        $contenido = (string) $r->getContent();
        $tipo = (string) $r->headers->get('Content-Type');
        if (str_contains($tipo, 'json') && is_array($json = json_decode($contenido, true))) {
            return $base + ['tipo' => 'json', 'cuerpo' => $json];
        }

        return $base + ['tipo' => 'texto', 'cuerpo' => $this->textoDeHtml($contenido)];
    }

    /** Un archivo que devolvió la función (un reporte, un PDF, un Excel): se guarda para descargarlo. */
    private function guardarArchivo(Response $r, string $disposicion, User $user, ?Conversacion $conversacion): array
    {
        $nombre = 'archivo';
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposicion, $m)) {
            $nombre = rawurldecode(trim($m[1]));
        } elseif ($r instanceof BinaryFileResponse) {
            $nombre = $r->getFile()->getFilename();
        }
        $nombre = trim(preg_replace('/[\x00-\x1F\/\\\\]+/', ' ', basename($nombre))) ?: 'archivo';
        if (!pathinfo($nombre, PATHINFO_EXTENSION)) {
            $nombre .= '.bin';
        }

        $adjunto = $this->adjuntos->crearGenerado($user, $conversacion, $nombre, function (string $destino) use ($r) {
            if ($r instanceof BinaryFileResponse) {
                copy($r->getFile()->getPathname(), $destino);

                return;
            }
            ob_start();
            $r->sendContent();
            file_put_contents($destino, (string) ob_get_clean());
        });

        return [
            'tipo'   => 'archivo',
            'cuerpo' => "La función devolvió el archivo «{$adjunto->nombre}».",
            'enlace' => "/ia/asistente/adjuntos/{$adjunto->id}/descargar",
        ];
    }

    private function recogerAvisos($sesion, array $flashAntes): array
    {
        $nuevos = array_diff((array) $sesion->get('_flash.new', []), $flashAntes);
        $avisos = [];
        foreach ($nuevos as $clave) {
            $valor = $sesion->get($clave);
            if ($clave === 'errors' && is_object($valor) && method_exists($valor, 'all')) {
                $avisos['errors'] = $valor->all();
            } elseif (in_array($clave, self::AVISOS, true) || is_scalar($valor)) {
                $avisos[$clave] = is_scalar($valor) ? (string) $valor : $valor;
            }
            $sesion->forget($clave);
        }
        $sesion->put('_flash.new', $flashAntes);

        return $avisos;
    }

    private function rutaInterna(string $url): string
    {
        $partes = parse_url($url);

        return ($partes['path'] ?? '/') . (isset($partes['query']) ? '?' . $partes['query'] : '');
    }

    private function textoDeHtml(string $html): string
    {
        if (preg_match('#<title>(.*?)</title>#is', $html, $m)) {
            $titulo = trim(html_entity_decode($m[1]));
        }
        $html = preg_replace('#<(script|style|svg|head|nav|aside|footer)\b[^>]*>.*?</\1>#is', ' ', $html);
        $texto = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/div|/tr|/li|/h\d)[^>]*>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = trim(preg_replace("/\n\s*\n+/", "\n", preg_replace('/[ \t]+/', ' ', $texto)));

        return (isset($titulo) ? "Pantalla: {$titulo}\n" : '') . mb_substr($texto, 0, 8000);
    }

    /** @return array{0:mixed, 1:mixed} la ruta y petición «actuales» del router (Route::current()). */
    private function rutaActual(): array
    {
        $leer = fn () => [$this->current ?? null, $this->currentRequest ?? null];

        return \Closure::bind($leer, $this->router, Router::class)();
    }

    private function fijarRutaActual($ruta, $peticion): void
    {
        $fijar = function ($r, $p) {
            $this->current = $r;
            $this->currentRequest = $p;
        };
        \Closure::bind($fijar, $this->router, Router::class)($ruta, $peticion);
    }
}
