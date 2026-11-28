<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuenta de solo consulta (16-nov-2026, pedido de David para Yordanis): quien tiene el rol «Solo lectura»
 * ve todas las pantallas que sus permisos le abren, pero no puede cambiar nada.
 *
 * Los permisos solos no alcanzan: muchas pantallas leen con POST (las tablas, los «get-…») y algunas
 * acciones fuertes corren con GET (procesar facturación, suspender clientes). Por eso se decide por el
 * método del controlador que atiende la ruta:
 *  - PUT, PATCH y DELETE: nunca.
 *  - POST: solo si el método es de lectura (table, index, get…, search, preview…) y su nombre no trae
 *    ninguna acción (store, update, import, sync…). Una ruta sin controlador (closure) no pasa.
 *  - GET: pasa, salvo los métodos que ejecutan algo (suspend, sync, process, delete…).
 * Salir de la sesión siempre se puede. Antes de resolver la ruta (middleware global, peticiones con token)
 * no se sabe el método: ahí solo pasan las lecturas por GET.
 */
class SoloLectura
{
    public const ROL = 'Solo lectura';

    public const MENSAJE = 'Tu cuenta es de solo consulta: puedes ver todo, pero no guardar ni cambiar nada.';

    /** Métodos de controlador que solo leen. */
    private const LECTURA = '/^(index|show|table|tabla|list|listar|listado|get|search|buscar|options|opciones|preview|previa|vistaPrevia|data|datos|find|count|contar|export|exportar|download|descargar|ver|consult|consultar|filter|filtrar|obtener|cargar|mostrar|detalle|detail|stats|estadisticas|resumen|catalog|catalogo|lookup|autocomplete|fetch|load|query|paginate|select)/i';

    /** Palabras de acción: si el nombre del método trae una, no es lectura aunque empiece como tal. */
    private const ACCION = '/(store|save|guardar|update|actualizar|create|crear|add|agregar|delete|destroy|eliminar|borrar|remove|quitar|edit|editar|import|assign|asign|assing|send|enviar|sync|sincron|aprob|approve|reject|rechaz|cancel|cerrar|close|toggle|activ|desactiv|restore|reset|upload|subir|generat|generar|process|procesar|apply|aplicar|pay|pagar|cobr|move|mover|change|cambiar|mark|marcar|regist|convert|duplic|clone|run|ejecut|execute|trigger|reprogram|checkin|checkout|confirm|firm|sign|valid|liquid|calcul|reserv|accept|acept|attach|detach|link|ligar|unlink|order|ordenar|sort|set|put|post|login|logout|refresh|renew|start|stop|finish|complet|iniciar|terminar)/i';

    /** Métodos que con un GET ejecutan algo (facturar, suspender, sincronizar, borrar…). */
    private const ACCION_GET = '/(destroy|delete|eliminar|borrar|sync|sincron|enviar|send|aprob|approve|reject|rechaz|toggle|activar|desactivar|import|ejecut|^run|execute|trigger|cobrar|pagar|apply|aplicar|process|procesar|generar|generate|restore|reset|mark|marcar|leid|clear|limpiar|purge|deploy|reboot|reiniciar|autoriz|authoriz|(?-i:^set[A-Z])|(?-i:^create[A-Z]))/i';

    public function handle(Request $request, Closure $next): Response
    {
        // En el grupo 'web' (ya hay sesión), el usuario de la sesión. En el global, antes de la sesión, solo
        // se mira quién es si la petición trae token (la app): así no se toca la sesión antes de tiempo.
        $user = $request->hasSession()
            ? $request->user()
            : ($request->bearerToken() ? auth('sanctum')->user() : null);
        // permitido() no consulta la BD: se evalúa primero para que las lecturas normales no paguen la consulta de roles.
        if (! $user || $this->permitido($request) || ! method_exists($user, 'hasRole') || ! $user->hasRole(self::ROL)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => self::MENSAJE, 'solo_lectura' => true], 403);
        }
        if ($request->isMethod('GET')) {
            abort(403, self::MENSAJE);
        }

        return redirect()->back()->with('error', self::MENSAJE);
    }

    private function permitido(Request $request): bool
    {
        $ruta = $request->route();
        if ($ruta && in_array($ruta->getName(), ['logout'], true) || $request->is('logout')) {
            return true;
        }
        $metodo = $ruta && is_string($ruta->getAction('uses')) ? $ruta->getActionMethod() : null;

        return self::esLectura($request->method(), $metodo);
    }

    /**
     * ¿Esta petición solo lee? Por el verbo HTTP y el método del controlador que la atiende (null =
     * closure o sin ruta). La misma regla la usa JARVIS para decidir qué funciones del sistema puede
     * consultar directo y cuáles cambian algo y necesitan la confirmación del usuario.
     */
    public static function esLectura(string $verbo, ?string $metodo): bool
    {
        return match (strtoupper($verbo)) {
            'GET', 'HEAD', 'OPTIONS' => ! $metodo || ! preg_match(self::ACCION_GET, $metodo),
            'POST'                   => $metodo && preg_match(self::LECTURA, $metodo) && ! preg_match(self::ACCION, $metodo),
            default                  => false,
        };
    }
}
