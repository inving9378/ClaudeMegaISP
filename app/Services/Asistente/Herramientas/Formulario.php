<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;

/**
 * Arma la MISMA petición que manda una pantalla de alta (su FormRequest, con sus reglas) para que
 * JARVIS pase por la validación y el guardado de siempre en vez de copiarlos.
 *
 * Ojo: esas reglas leen la petición EN CURSO (`rules(Request $request)` la resuelve el contenedor,
 * y algunas reglas reciben `$request` en su constructor). En curso está la petición del chat, no
 * los datos del alta, así que mientras se valida y se guarda se pone esta petición como la actual
 * y al terminar se regresa la original.
 */
class Formulario
{
    /** @param class-string<FormRequest> $clase */
    public static function peticion(string $clase, string $uri, array $datos, User $user): FormRequest
    {
        $req = $clase::create($uri, 'POST', $datos);
        $req->setContainer(app())->setRedirector(app('redirect'));
        $route = (new Route('POST', ltrim($uri, '/'), []))->bind($req);
        $req->setRouteResolver(fn () => $route);
        $req->setUserResolver(fn () => $user);
        if (app()->bound('session.store')) {
            $req->setLaravelSession(app('session.store'));
        }

        return $req;
    }

    /** Corre $fn con $req como la petición actual de la aplicación. */
    public static function comoPeticionActual(Request $req, callable $fn): mixed
    {
        $original = app('request');
        app()->instance('request', $req);
        Facade::clearResolvedInstance('request');
        try {
            return $fn();
        } finally {
            app()->instance('request', $original);
            Facade::clearResolvedInstance('request');
        }
    }

    /** null si los datos pasan las reglas de la pantalla; si no, los motivos en texto plano. */
    public static function errores(FormRequest $req): ?string
    {
        try {
            self::comoPeticionActual($req, fn () => $req->validateResolved());

            return null;
        } catch (ValidationException $e) {
            return self::textoPlano(collect($e->errors())->flatten()->unique()->implode(' '));
        }
    }

    /**
     * Algunos mensajes de las reglas traen HTML (listas de homónimos, enlace al que ya existe): se
     * pasan a texto, y los enlaces internos a la forma [texto](/ruta) que entiende JARVIS.
     */
    public static function textoPlano(string $mensaje): string
    {
        $mensaje = preg_replace('#<a\s[^>]*href="(/[^"]*)"[^>]*>(.*?)</a>#is', '[$2]($1)', $mensaje) ?? $mensaje;
        $mensaje = str_ireplace(['<li>', '</li>', '<ul>', '</ul>'], [' · ', '', ': ', ' '], $mensaje);

        return trim(preg_replace('/\s+/', ' ', strip_tags($mensaje)));
    }
}
