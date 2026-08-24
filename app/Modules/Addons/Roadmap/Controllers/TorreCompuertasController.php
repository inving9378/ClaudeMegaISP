<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\TorreCompuertaCambio;
use App\Modules\Addons\Roadmap\Services\CompuertasService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de compuertas de la Torre — estado y control.
 *
 * REGLA 3 (la lección del botón RUN MIGRATIONS): ninguna acción se ejecuta con un clic
 * suelto. El cliente debe mandar `confirmado=true` junto con la clave de la acción, y el
 * servidor lo exige aunque la UI ya haya preguntado — la confirmación es del servidor, no
 * un adorno del frontend. Toda acción queda en `torre_compuerta_cambios`.
 *
 * REGLA 6: sin permiso no se renderiza ni responde. Roles `super-administrator` y
 * `DESARROLLADOR`.
 */
class TorreCompuertasController extends Controller
{
    public function __construct(
        private CompuertasService $compuertas,
        private RoadmapCircuitoService $circuito,
    ) {
    }

    /** Puerta de entrada: sin acceso a la Torre no se ve nada. */
    private function autorizar(): void
    {
        $u = auth()->user();
        if (! $u || ! ($u->hasRole('super-administrator') || $u->hasRole('DESARROLLADOR'))) {
            abort(403, 'El tablero de compuertas es solo para super-administrator o DESARROLLADOR.');
        }
    }

    /** Estado medido en vivo. */
    public function estado(): JsonResponse
    {
        $this->autorizar();

        return response()->json($this->compuertas->tablero());
    }

    /** Bitácora de cambios, consultable desde el mismo tablero (regla 4). */
    public function bitacora(Request $request): JsonResponse
    {
        $this->autorizar();

        $q = TorreCompuertaCambio::query()->orderByDesc('id');
        if ($c = $request->query('compuerta')) {
            $q->where('compuerta', $c);
        }

        return response()->json([
            'cambios' => $q->limit(100)->get()->map(fn ($c) => [
                'id'        => $c->id,
                'cuando'    => optional($c->created_at)->toDateTimeString(),
                'quien'     => $c->user_login ?: '—',
                'compuerta' => $c->compuerta,
                'accion'    => $c->accion,
                'de'        => $c->valor_antes,
                'a'         => $c->valor_despues,
                'detalle'   => $c->detalle,
                'ip'        => $c->ip,
            ]),
        ]);
    }

    /**
     * Ejecuta una acción de compuerta. Segundo paso obligatorio: `confirmado`.
     * El primer paso (mostrar qué va a pasar) lo hace la UI leyendo `confirmar` de
     * la propia acción; este endpoint solo acepta la llamada ya confirmada.
     */
    public function accion(Request $request): JsonResponse
    {
        $this->autorizar();

        $datos = $request->validate([
            'accion'     => 'required|string|max:40',
            'confirmado' => 'required|boolean',
            'valor'      => 'nullable|string|max:40',
            'ids'        => 'nullable|array',
            'ids.*'      => 'integer',
        ]);

        if (! $datos['confirmado']) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Esta acción necesita confirmación explícita en un segundo paso.',
            ], 422);
        }

        return match ($datos['accion']) {
            'pausar', 'reanudar'    => $this->accionPausa($datos['accion']),
            'nivel'                 => $this->accionNivel((string) ($datos['valor'] ?? '')),
            'auditor_encender'      => $this->accionAuditor(true),
            'auditor_apagar'        => $this->accionAuditor(false),
            'reactivar_agendados'   => $this->accionReactivarAgendados(),
            'soltar_items'          => $this->accionSoltarItems($datos['ids'] ?? []),
            default                 => response()->json(['ok' => false, 'mensaje' => 'Acción desconocida.'], 422),
        };
    }

    // ---------------------------------------------------------------- acciones

    private function accionPausa(string $accion): JsonResponse
    {
        $u = auth()->user();
        if (! $u->can('circuito.pause')) {
            return $this->sinPermiso('circuito.pause');
        }

        $antes = $this->circuito->isPaused() ? 'PAUSADO' : 'suelto';
        // setPaused() exige sesión con permiso: por eso esta acción vive en HTTP y no en CLI.
        $this->circuito->setPaused($accion === 'pausar');
        $despues = $this->circuito->isPaused() ? 'PAUSADO' : 'suelto';

        TorreCompuertaCambio::registrar('pausa', $accion, $antes, $despues);

        return response()->json(['ok' => true, 'mensaje' => "Freno de mano: {$antes} → {$despues}."]);
    }

    private function accionNivel(string $valor): JsonResponse
    {
        $u = auth()->user();
        if (! $u->can('torre.config.edit')) {
            return $this->sinPermiso('torre.config.edit');
        }
        if (! in_array($valor, ['manual', 'asistido', 'autonomo'], true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Nivel inválido.'], 422);
        }

        $antes = (string) DB::table('torre_config')->value('nivel_automatizacion');
        DB::table('torre_config')->update(['nivel_automatizacion' => $valor, 'updated_at' => now()]);

        TorreCompuertaCambio::registrar('nivel', 'nivel', $antes, $valor);

        return response()->json(['ok' => true, 'mensaje' => "Nivel del autopilot: {$antes} → {$valor}."]);
    }

    private function accionAuditor(bool $encender): JsonResponse
    {
        $u = auth()->user();
        if (! $u->can('torre.config.edit')) {
            return $this->sinPermiso('torre.config.edit');
        }

        $antes = ((bool) DB::table('torre_config')->value('auditor_activo')) ? 'activo' : 'apagado';
        DB::table('torre_config')->update(['auditor_activo' => $encender, 'updated_at' => now()]);
        $despues = $encender ? 'activo' : 'apagado';

        TorreCompuertaCambio::registrar('auditor', $encender ? 'auditor_encender' : 'auditor_apagar', $antes, $despues);

        return response()->json(['ok' => true, 'mensaje' => "Auditor: {$antes} → {$despues}."]);
    }

    private function accionReactivarAgendados(): JsonResponse
    {
        $u = auth()->user();
        if (! $u->can('torre.config.edit')) {
            return $this->sinPermiso('torre.config.edit');
        }

        $vencidos = RoadmapItem::query()
            ->whereNotNull('agendado_para')->where('agendado_para', '<=', now())->pluck('id')->all();

        if ($vencidos === []) {
            return response()->json(['ok' => true, 'mensaje' => 'No hay agendados vencidos que reactivar.']);
        }

        RoadmapItem::query()->whereIn('id', $vencidos)->update(['agendado_para' => null, 'updated_at' => now()]);

        TorreCompuertaCambio::registrar(
            'agendados', 'reactivar_agendados',
            count($vencidos) . ' agendados', '0 agendados',
            'ids: #' . implode(', #', $vencidos)
        );

        return response()->json(['ok' => true, 'mensaje' => count($vencidos) . ' item(s) reactivados.']);
    }

    private function accionSoltarItems(array $ids): JsonResponse
    {
        $u = auth()->user();
        if (! $u->can('torre.config.edit')) {
            return $this->sinPermiso('torre.config.edit');
        }
        if ($ids === []) {
            return response()->json(['ok' => false, 'mensaje' => 'No se indicaron items.'], 422);
        }

        $antes = RoadmapItem::query()->whereIn('id', $ids)
            ->get(['id', 'worker_sid'])->map(fn ($i) => "#{$i->id}={$i->worker_sid}")->implode(', ');

        RoadmapItem::query()->whereIn('id', $ids)->update([
            'worker_sid'        => null,
            'estado_aprobacion' => 'aprobado_irving',
            'updated_at'        => now(),
        ]);

        TorreCompuertaCambio::registrar('reservados', 'soltar_items', $antes, 'sin worker_sid', 'ids: #' . implode(', #', $ids));

        return response()->json(['ok' => true, 'mensaje' => count($ids) . ' item(s) liberados.']);
    }

    private function sinPermiso(string $permiso): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'mensaje' => "Falta el permiso `{$permiso}`. Pídeselo a Irving o córrelo desde consola.",
        ], 403);
    }
}
