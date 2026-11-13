<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Support\OrdenEquipo;
use App\Modules\Addons\Talento\Support\OrdenReferencia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revisión del supervisor desde la app (13-nov-2026, sobre #1286). Irving: la orden terminada pasa
 * por el supervisor, que la valida (y ahí cuentan sus puntos) o se la devuelve al técnico con una
 * nota. En la web ya estaba; en la app hace falta porque si el supervisor no está en la oficina la
 * orden no se cierra.
 *
 * Quién: con el permiso de validar órdenes y la revisión del supervisor encendida. Qué ve: quien
 * gestiona órdenes, las de todos; un supervisor, las de su equipo directo (como la lista de la web).
 * Nunca las suyas: una orden la valida otra persona.
 */
class RevisionOrdenesAppService
{
    public function __construct(
        private OrdenTrabajoUnifiedService $unified,
        private CorreccionEvidenciasService $correcciones,
        private AvisoOrdenColaborador $avisos,
    ) {}

    public function disponible(User $user): bool
    {
        return (bool) config('unificacion.interruptores.validacion_separada')
            && $user->can('talento.work_orders.validate');
    }

    public function total(User $user): int
    {
        return $this->disponible($user) ? $this->porValidar($user)->count() : 0;
    }

    /** Las que esperan su revisión, primero la que lleva más tiempo esperando. */
    public function pendientes(User $user, int $pagina = 1, int $porPagina = 20): array
    {
        if (! $this->disponible($user)) {
            return ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];
        }

        $p = $this->porValidar($user)->with(['talentoType', 'users'])
            ->orderBy('finish_at')->orderBy('id')
            ->paginate($porPagina, ['*'], 'page', $pagina);
        $resumenes = $this->unified->resumenesDeTareas($p->getCollection());

        return [
            'data' => $p->getCollection()->map(fn (Task $t) => $this->item($t, $resumenes[$t->id]))->values()->all(),
            'total' => $p->total(),
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
        ];
    }

    /** El detalle que ve el técnico (fotos, datos de cierre, material usado) más quién la hizo y qué puede hacer. */
    public function detalle(User $user, int $id): ?array
    {
        $task = $this->ordenes($user)->with(['talentoType', 'users'])->find($id);
        if (! $task) {
            return null;
        }
        $porValidar = $task->status === 'Done' && ! $task->validated_at && $this->disponible($user);

        return array_merge($this->unified->detalleDeTarea($task), $this->personas($task), [
            'terminada_at' => $this->fecha($task->finish_at),
            'puntos' => (float) $task->points,
            'puede_validar' => $porValidar,
            'puede_devolver' => $porValidar,
        ]);
    }

    public function validar(User $user, int $id): array
    {
        if (! $this->porValidar($user)->whereKey($id)->exists()) {
            return $this->yaNoEspera();
        }
        $r = $this->unified->validarAdmin($id, OrdenReferencia::ORIGEN_TAREA);

        return $r['success'] ? ['success' => true] : $r;
    }

    /** La regresa al técnico En curso con la nota (que ve en la app) y le avisa, igual que la web. */
    public function devolver(User $user, int $id, string $motivo): array
    {
        $task = $this->porValidar($user)->with(['talentoType', 'users'])->find($id);
        if (! $task) {
            return $this->yaNoEspera();
        }
        $r = $this->correcciones->devolver($task, $motivo, $user->id);
        if (! $r['success']) {
            return $r;
        }
        $tipo = $task->talentoType?->name ?? 'Orden de trabajo';
        foreach ($task->users as $usuario) {
            $this->avisos->devuelta($task, $usuario, $tipo, $motivo);
        }

        return ['success' => true];
    }

    /**
     * Para los avisos del teléfono: cada orden que espera su revisión. Una devuelta que el técnico
     * corrigió cambia de id (lleva el de su corrección), así se vuelve a avisar.
     */
    public function avisos(User $user): array
    {
        if (! $this->disponible($user)) {
            return [];
        }
        $abiertas = $this->correcciones->abiertasPorTarea();

        return $this->porValidar($user)->with(['talentoType', 'users'])
            ->orderByDesc('finish_at')->limit(30)->get()
            ->map(function (Task $t) use ($abiertas) {
                $c = $abiertas[$t->id] ?? null;
                $corregida = $c && $c['estado'] === 'enviada';

                return [
                    'id' => $t->id . ($corregida ? '-c' . $c['id'] : ''),
                    'ot_id' => $t->id,
                    'titulo' => 'Orden #' . $t->id . ($t->talentoType ? " · {$t->talentoType->name}" : ''),
                    'tecnico' => $this->personas($t)['tecnico'],
                    'corregida' => $corregida,
                ];
            })->values()->all();
    }

    /** Ids de usuario cuyas órdenes revisa; null = todas (quien gestiona órdenes, mismo criterio que la web). */
    private function alcance(User $user): ?array
    {
        if ($user->can('talento.work_orders.manage') || $user->can('talento.employees.view')) {
            return null;
        }
        $yo = Actor::for($user)->talento();

        return $yo ? $yo->subordinados()->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all() : [];
    }

    /** Órdenes de su alcance (de `tasks`: las antiguas no tienen este flujo) en las que no participa. */
    private function ordenes(User $user): Builder
    {
        $alcance = $this->alcance($user);

        return Task::query()
            ->whereNotNull('talento_type_id')
            ->when($alcance !== null, fn ($q) => $q->whereHas('users', fn ($u) => $u->whereIn('users.id', $alcance ?: [0])))
            ->whereDoesntHave('users', fn ($u) => $u->where('users.id', $user->id));
    }

    private function porValidar(User $user): Builder
    {
        return $this->ordenes($user)->where('status', 'Done')->whereNull('validated_at');
    }

    private function item(Task $task, array $resumen): array
    {
        $correccion = $resumen['correccion'];

        return array_merge([
            'id' => $task->id,
            'folio' => $task->id,
            'tipo' => $resumen['tipo'],
            'titulo' => $task->title,
            'cliente' => $resumen['cliente'],
            'direccion' => $resumen['direccion'],
        ], $this->personas($task), [
            'terminada_at' => $this->fecha($task->finish_at),
            'puntos' => (float) $task->points,
            // La devolvió y el técnico ya la corrigió: espera otra vez su revisión.
            'corregida' => ($correccion['estado'] ?? null) === 'enviada',
        ]);
    }

    /** El responsable de la orden y, si fue en equipo, sus auxiliares. */
    private function personas(Task $task): array
    {
        $nombre = fn ($u) => trim($u->name . ' ' . $u->father_last_name);
        $responsableId = OrdenEquipo::responsableUserId($task);
        $responsable = $task->users->firstWhere('id', $responsableId) ?? $task->users->first();

        return [
            'tecnico' => $responsable ? $nombre($responsable) : null,
            'auxiliares' => $task->users->reject(fn ($u) => $u->id === $responsable?->id)->map($nombre)->values()->all(),
        ];
    }

    private function fecha($valor): ?string
    {
        return $valor ? Carbon::parse($valor)->format('Y-m-d H:i') : null;
    }

    private function yaNoEspera(): array
    {
        return ['success' => false, 'message' => 'Esta orden ya no espera tu revisión.', 'status_code' => 404];
    }
}
