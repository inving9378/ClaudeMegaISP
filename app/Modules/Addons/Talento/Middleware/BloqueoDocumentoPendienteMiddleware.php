<?php

namespace App\Modules\Addons\Talento\Middleware;

use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use App\Modules\Addons\Talento\Support\Actor;
use Closure;
use Illuminate\Http\Request;

/**
 * Item #9990804 — bloqueo PROPORCIONAL (por módulo, no total) de escrituras cuando el
 * colaborador autenticado tiene un documento tipo='firma' con status='pendiente'.
 *
 * Global (Kernel 'web' group): corre en TODAS las rutas del guard web, pero solo actúa si
 * está prendido el kill switch `talento.bloqueo_firma_pendiente_enabled` (default OFF).
 * Solo intercepta escrituras (POST/PUT/PATCH/DELETE); GET siempre pasa.
 */
class BloqueoDocumentoPendienteMiddleware
{
    /**
     * Rutas SIEMPRE exentas, por NOMBRE de ruta (nunca por substring de URL), para que el
     * colaborador bloqueado pueda resolver su propio bloqueo o gestionar su cuenta.
     */
    private const RUTAS_EXENTAS = [
        'login',
        'logout',
        'password.email',
        'password.update',
        'profile.password.change',
        'talento.documentos.firma',
        'talento.asistencia.checkin',
        'talento.asistencia.checkout',
        'talento.asistencia.ping',
        'talento.portal.asistencia.checkin',
        'talento.portal.asistencia.checkout',
    ];

    private const METODOS_INTERCEPTADOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next)
    {
        if (! config('talento.bloqueo_firma_pendiente_enabled')) {
            return $next($request);
        }

        if (! in_array($request->method(), self::METODOS_INTERCEPTADOS, true)) {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();
        if ($routeName !== null && in_array($routeName, self::RUTAS_EXENTAS, true)) {
            return $next($request);
        }

        if (! auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        if ($user->can('talento.bypass_bloqueo_firma')) {
            return $next($request);
        }

        $colaborador = Actor::for($user)->talento();
        if (! $colaborador) {
            return $next($request);
        }

        $pendientes = TalentoEmployeeDocument::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('status', 'pendiente')
            ->whereHas('template', fn ($q) => $q->where('tipo', 'firma'))
            ->with('template')
            ->get();

        if ($pendientes->isEmpty()) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');
        $bloqueantes = $pendientes->filter(fn ($doc) => $this->bloqueaRuta($doc->template->modulos_bloqueados ?? [], $path));

        if ($bloqueantes->isEmpty()) {
            return $next($request);
        }

        return response()->json([
            'bloqueado' => true,
            'motivo' => 'documento_firma_pendiente',
            'documentos_pendientes' => $bloqueantes->values()->map(fn ($doc) => [
                'id' => $doc->id,
                'template_id' => $doc->template_id,
                'nombre' => $doc->template->name,
            ]),
        ], 423);
    }

    /**
     * Sentinel '*' = TODO lo operativo (bloquea cualquier escritura no exenta). De lo contrario,
     * solo bloquea si la ruta cae bajo alguno de los prefijos del catálogo `modulos_operativos`
     * para los módulos listados.
     */
    private function bloqueaRuta(array $modulosBloqueados, string $path): bool
    {
        if (in_array('*', $modulosBloqueados, true)) {
            return true;
        }

        $catalogo = (array) config('talento.modulos_operativos', []);

        foreach ($modulosBloqueados as $modulo) {
            foreach ((array) ($catalogo[$modulo] ?? []) as $prefijo) {
                if (str_starts_with($path, $prefijo)) {
                    return true;
                }
            }
        }

        return false;
    }
}
