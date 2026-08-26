<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\TorreCompuertaCambio;
use App\Modules\Addons\Roadmap\Services\CompuertasService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use App\Modules\Addons\Roadmap\Support\CatalogoPermisosCircuito;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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


    /**
     * Pestaña de permisos: qué habilita cada permiso del circuito, qué se recomienda
     * para cada rol, qué está concedido hoy de verdad (Spatie manda), y la última
     * decisión registrada de Irving.
     */
    public function permisos(): JsonResponse
    {
        $this->autorizar();

        $decisiones = DB::table('torre_permiso_decisiones')->get()->keyBy(fn ($d) => $d->permiso . '|' . $d->rol);
        $filas = [];

        foreach (CatalogoPermisosCircuito::PERMISOS as $nombre => $meta) {
            $existe = Permission::where('name', $nombre)->where('guard_name', 'web')->exists();
            $porRol = [];

            foreach (CatalogoPermisosCircuito::ROLES as $rol) {
                $rolModel = Role::where('name', $rol)->where('guard_name', 'web')->first();
                $tiene    = $existe && $rolModel && $rolModel->hasPermissionTo($nombre);
                $rec      = CatalogoPermisosCircuito::recomendacion($nombre, $rol);
                $d        = $decisiones->get($nombre . '|' . $rol);

                $porRol[$rol] = [
                    'concedido'     => $tiene,
                    'recomendacion' => $rec,
                    // Se marca la discrepancia contra el estado REAL, no contra la decisión
                    // guardada: lo que importa es si hoy el sistema contradice el consejo.
                    'contradice'    => ($rec === 'conceder' && ! $tiene) || ($rec === 'negar' && $tiene),
                    'decision'      => $d ? [
                        'por'        => $d->decidido_por_login,
                        'cuando'     => $d->decidido_en,
                        'contradijo' => (bool) $d->contradice_recomendacion,
                        'nota'       => $d->nota,
                    ] : null,
                ];
            }

            $filas[] = [
                'permiso'      => $nombre,
                'existe'       => $existe,
                'habilita'     => $meta['habilita'],
                'consecuencia' => $meta['consecuencia'],
                'porque'       => $meta['porque'],
                'roles'        => $porRol,
            ];
        }

        return response()->json([
            'permisos'   => $filas,
            'roles'      => CatalogoPermisosCircuito::ROLES,
            'puede_editar' => (bool) auth()->user()?->can('torre.config.edit'),
        ]);
    }

    /**
     * Interruptor. Concede o revoca de verdad en Spatie y registra la decisión con
     * fecha y autor — se guarda aunque contradiga la recomendación, que es justamente
     * el caso que hay que poder auditar después.
     */
    public function permisoToggle(Request $request): JsonResponse
    {
        $this->autorizar();

        $datos = $request->validate([
            'permiso'    => 'required|string|max:120',
            'rol'        => 'required|string|max:60',
            'conceder'   => 'required|boolean',
            'confirmado' => 'required|boolean',
            'nota'       => 'nullable|string|max:500',
        ]);

        if (! $datos['confirmado']) {
            return response()->json(['ok' => false, 'mensaje' => 'Esta acción necesita confirmación explícita.'], 422);
        }
        if (! auth()->user()->can('torre.config.edit')) {
            return $this->sinPermiso('torre.config.edit');
        }
        if (! array_key_exists($datos['permiso'], CatalogoPermisosCircuito::PERMISOS)
            || ! in_array($datos['rol'], CatalogoPermisosCircuito::ROLES, true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Permiso o rol fuera del catálogo del circuito.'], 422);
        }

        $rol = Role::where('name', $datos['rol'])->where('guard_name', 'web')->first();
        if (! $rol) {
            return response()->json(['ok' => false, 'mensaje' => "El rol {$datos['rol']} no existe."], 422);
        }

        $permiso = Permission::firstOrCreate(['name' => $datos['permiso'], 'guard_name' => 'web']);
        $antes   = $rol->hasPermissionTo($permiso) ? 'concedido' : 'revocado';

        $datos['conceder'] ? $rol->givePermissionTo($permiso) : $rol->revokePermissionTo($permiso);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $rec        = CatalogoPermisosCircuito::recomendacion($datos['permiso'], $datos['rol']);
        $contradice = ($rec === 'conceder' && ! $datos['conceder']) || ($rec === 'negar' && $datos['conceder']);
        $u          = auth()->user();

        DB::table('torre_permiso_decisiones')->updateOrInsert(
            ['permiso' => $datos['permiso'], 'rol' => $datos['rol']],
            [
                'concedido'                => $datos['conceder'],
                'recomendacion'            => $rec,
                'contradice_recomendacion' => $contradice,
                'nota'                     => $datos['nota'] ?? null,
                'decidido_por'             => $u->id,
                'decidido_por_login'       => $u->login_user ?? $u->name,
                'decidido_en'              => now(),
                'updated_at'               => now(),
                'created_at'               => now(),
            ]
        );

        TorreCompuertaCambio::registrar(
            'permisos',
            $datos['conceder'] ? 'conceder' : 'revocar',
            "{$datos['rol']}: {$antes}",
            "{$datos['rol']}: " . ($datos['conceder'] ? 'concedido' : 'revocado'),
            $datos['permiso'] . ($contradice ? ' (CONTRADICE la recomendación: ' . $rec . ')' : '')
        );

        return response()->json([
            'ok'         => true,
            'mensaje'    => "{$datos['permiso']} para {$datos['rol']}: {$antes} → " . ($datos['conceder'] ? 'concedido' : 'revocado') . '.',
            'contradice' => $contradice,
        ]);
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
