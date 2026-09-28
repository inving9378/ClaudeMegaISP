<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoCustodiaController extends Controller
{
    public function index()
    {
        $this->authorize('talento.custody.view');
        return view('addon-talento::talento.custodia');
    }

    /**
     * `talento.custody.view` está clasificado "context=portal" (migración
     * classify_portal_permissions, decisión de Irving: "EXCLUSIVAS del
     * colaborador en su app/portal") — por eso también lo tienen
     * TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR directamente. Pero
     * show() no tenía NINGÚN scoping propio, así que ese permiso
     * "solo lo mío" terminaba abriendo la custodia de CUALQUIER
     * colaborador para cualquier técnico (contradice la propia
     * clasificación). talento.employees.view sí es la señal real de
     * "staff que ve a cualquiera" (admin/DESARROLLADOR/Mostrador — el
     * resto de roles admin pasa por el bypass de CheckRoutePermission
     * antes de llegar aquí).
     */
    private function puedeVerCustodiaDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.employees.view')) {
            return true;
        }
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) return false;
        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

    /**
     * Returns inventory items in custody for a specific collaborator (read-only).
     * Consumes inventory_item_stocks (modelable_type = App\Models\User) without new tables.
     */
    public function show($colaboradorId)
    {
        abort_unless($this->puedeVerCustodiaDe($colaboradorId), 403);

        $colaborador = TalentoColaborador::with('user')->findOrFail($colaboradorId);
        $userId = $colaborador->user_id;

        // Bug real encontrado 28-sep: el JOIN a inventory_categories/
        // i.category_id apuntaba a tabla/columna que NUNCA existieron en
        // este esquema — tronaba 500 SIEMPRE, para cualquier colaborador
        // (mismo hallazgo, ya documentado en un comentario de
        // EmployeeDocumentPackageService::herramientasData(), que nunca se
        // aplicó aquí). i.sku/i.unit tampoco existen. Columnas reales:
        // inventory_items.serial_number (no "sku") + inventory_item_types
        // .categoria (FK inventory_item_type_id, no inventory_categories)
        // para la categoría real (herramienta/material/equipo_cliente/
        // equipo_red).
        $stocks = DB::table('inventory_item_stocks as s')
            ->join('inventory_items as i', 'i.id', '=', 's.inventory_item_id')
            ->leftJoin('inventory_item_types as t', 't.id', '=', 'i.inventory_item_type_id')
            ->where('s.modelable_type', 'App\\Models\\User')
            ->where('s.modelable_id', $userId)
            ->whereNull('s.deleted_at')
            ->where('s.current_stock', '>', 0)
            ->select(
                's.id as stock_id',
                'i.id as item_id',
                'i.name as item_name',
                'i.serial_number',
                't.categoria as category',
                's.current_stock',
                's.condition',
                's.unit_cost',
                's.created_at as assigned_at'
            )
            ->orderBy('t.categoria')
            ->orderBy('i.name')
            ->get();

        // Recent movements to/from this user
        $movements = DB::table('inventory_movements as m')
            ->join('inventory_items as i', 'i.id', '=', 'm.inventory_item_id')
            ->where(function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('m.movementable_to_type', 'App\\Models\\User')
                        ->where('m.movementable_to_id', $userId);
                })->orWhere(function ($q2) use ($userId) {
                    $q2->where('m.movementable_from_type', 'App\\Models\\User')
                        ->where('m.movementable_from_id', $userId);
                });
            })
            ->whereNull('m.deleted_at')
            ->select(
                'm.id',
                'm.type',
                'm.quantity',
                'm.description',
                'i.name as item_name',
                'm.created_at'
            )
            ->orderBy('m.created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'colaborador' => [
                'id'   => $colaborador->id,
                'name' => $colaborador->user->name ?? '—',
                'type' => $colaborador->type,
            ],
            'stocks'    => $stocks,
            'movements' => $movements,
        ]);
    }
}
