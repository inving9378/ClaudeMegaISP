<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Requests\CambiarEstadoCampanaRequest;
use App\Modules\Addons\Marketing\Requests\GuardarCampana360Request;
use App\Modules\Addons\Marketing\Services\Campanas360\Campana360Presenter;
use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Campañas 360 (Fase 2): índice, alta, ficha, edición, borrado y cambio de estado. Rutas `$M` (regla de acceso de la
 * Fase 1: solo admin de Meganet). La campaña se busca SIEMPRE con el scope de empresa: un id de otra empresa → 404.
 * Ver §2.4 de docs/marketing/fase-2-paquetes.md.
 */
class Campana360Controller extends Controller
{
    public const POR_PAGINA = 20;

    public function __construct(private readonly Campana360Presenter $presenter)
    {
    }

    /** 1) GET /api/marketing/campanas-360?estado=&pagina= */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['sometimes', 'required', 'string', 'in:' . implode(',', array_keys(CatalogosCampana360::ESTADOS_CAMPANA))],
            'pagina' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
        ], [
            'estado.*' => 'Estado no válido.',
            'pagina.*' => 'La página debe ser un número entero desde 1.',
        ]);

        $pagina = (int) ($datos['pagina'] ?? 1);
        $query  = self::query();

        if (isset($datos['estado'])) {
            $query->where('status', $datos['estado']);
        }

        $total     = (clone $query)->count();
        $campanas  = $query->orderByDesc('created_at')->orderByDesc('id')
            ->forPage($pagina, self::POR_PAGINA)->get();

        $filas = $campanas->isEmpty() ? collect() : GeneratedContent::query()
            ->whereIn('source_campaign_id', $campanas->pluck('id'))
            ->whereIn('pieza', array_merge(CatalogosCampana360::PIEZAS_TEXTO, CatalogosCampana360::PIEZAS_VIDEO))
            ->get(['id', 'source_campaign_id', 'pieza', 'status']);

        return response()->json([
            'ok'   => true,
            'data' => $campanas->map(fn (Campaign $c) => $this->presenter->resumen($c, $filas->where('source_campaign_id', $c->id)->values()))->all(),
            'meta' => ['pagina' => $pagina, 'por_pagina' => self::POR_PAGINA, 'total' => $total],
        ]);
    }

    /** 2) POST /api/marketing/campanas-360 */
    public function store(GuardarCampana360Request $request): JsonResponse
    {
        $datos = $request->datos();
        if (! array_key_exists('budget', $datos)) {
            // F-1: borrador sin presupuesto todavía (distinto de «orgánica en $0»).
            $datos['config'] = [Campana360Presenter::MARCA_PRESUPUESTO => false];
        }

        $campana = DB::transaction(function () use ($datos) {
            return Campaign::create($datos + [
                'slug'               => $this->slugUnico($datos['name']),
                'type'               => 'mixed',
                'status'             => 'draft',
                'currency'           => 'MXN',
                'spent'              => '0.00',
                'estrategia_estado'  => 'sin_generar',
                'creado_por_user_id' => auth()->id(),
            ]);
        });

        return response()->json(['ok' => true, 'data' => $this->presenter->campana($campana->fresh())], 201);
    }

    /** 3) GET /api/marketing/campanas-360/{id} */
    public function show(int $id): JsonResponse
    {
        $campana = self::buscar($id);

        if (! $campana) {
            return self::noEncontrada();
        }

        return response()->json(['ok' => true, 'data' => $this->presenter->ficha($campana)]);
    }

    /** 4) PUT /api/marketing/campanas-360/{id} */
    public function update(GuardarCampana360Request $request, int $id): JsonResponse
    {
        $campana = self::buscar($id);

        if (! $campana) {
            return self::noEncontrada();
        }

        if (! in_array($campana->status, ['draft', 'paused'], true)) {
            return self::negocio('campana_activa', 'Pausa la campaña para cambiar sus datos.', 409);
        }

        $campana->update($request->datos());

        return response()->json(['ok' => true, 'data' => $this->presenter->campana($campana->fresh())]);
    }

    /** 5) DELETE /api/marketing/campanas-360/{id} — solo borrador; borra piezas de texto, conserva videos. */
    public function destroy(int $id): JsonResponse
    {
        $campana = self::buscar($id);

        if (! $campana) {
            return self::noEncontrada();
        }

        if ($campana->status !== 'draft') {
            return self::negocio('solo_borrador', 'Solo se puede borrar una campaña en borrador.', 409);
        }

        DB::transaction(function () use ($campana) {
            GeneratedContent::query()
                ->where('source_campaign_id', $campana->id)
                ->whereIn('pieza', CatalogosCampana360::PIEZAS_TEXTO)
                ->get()
                ->each->delete();

            $campana->delete();
        });

        return response()->json(['ok' => true]);
    }

    /** 6) POST /api/marketing/campanas-360/{id}/estado */
    public function cambiarEstado(CambiarEstadoCampanaRequest $request, int $id): JsonResponse
    {
        $campana = self::buscar($id);

        if (! $campana) {
            return self::noEncontrada();
        }

        $nuevo  = (string) $request->validated('estado');
        $actual = (string) $campana->status;

        if (! in_array($nuevo, CatalogosCampana360::TRANSICIONES[$actual] ?? [], true)) {
            $de = CatalogosCampana360::ESTADOS_CAMPANA[$actual] ?? $actual;
            $a  = CatalogosCampana360::ESTADOS_CAMPANA[$nuevo] ?? $nuevo;

            return self::negocio('transicion_invalida', "No se puede pasar de {$de} a {$a}.", 422);
        }

        // F-1: para activar, la campaña debe estar completa (cancelar un borrador incompleto sí se puede).
        if ($nuevo === 'active' && ($faltantes = Campana360Presenter::faltantes($campana)) !== []) {
            return self::negocio('campana_incompleta', Campana360Presenter::mensajeFaltantes($faltantes), 422, ['faltantes' => $faltantes]);
        }

        if ($actual === 'draft' && $nuevo === 'active' && $campana->estrategia_estado !== 'lista') {
            return self::negocio('sin_estrategia', 'Genera la estrategia antes de activar la campaña.', 422);
        }

        $campana->update(['status' => $nuevo]);

        return response()->json(['ok' => true, 'data' => $this->presenter->campana($campana->fresh())]);
    }

    // ── Apoyos compartidos con los demás controladores de Campañas 360 ──────────────────────────

    /** Campañas 360 de la empresa activa (scope de empresa) — solo las creadas desde el asistente (objetivo). */
    public static function query(): Builder
    {
        return Campaign::query()->whereNotNull('objetivo');
    }

    public static function buscar(int $id): ?Campaign
    {
        return self::query()->find($id);
    }

    public static function noEncontrada(): JsonResponse
    {
        return self::negocio('no_encontrado', 'No encontramos esa campaña.', 404);
    }

    public static function negocio(string $codigo, string $mensaje, int $http, array $extra = []): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $codigo, 'mensaje' => $mensaje] + $extra, $http);
    }

    private function slugUnico(string $nombre): string
    {
        $base = Str::limit(Str::slug($nombre) ?: 'campana', 120, '');

        do {
            $slug = $base . '-' . Str::lower(Str::random(6));
        } while (Campaign::withTrashed()->withoutGlobalScopes()->where('company_id', Empresa::PRINCIPAL_ID)->where('slug', $slug)->exists());

        return $slug;
    }
}
