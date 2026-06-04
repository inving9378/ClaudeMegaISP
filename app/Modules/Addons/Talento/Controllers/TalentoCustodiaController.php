<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
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
     * Returns inventory items in custody for a specific collaborator (read-only).
     * Consumes inventory_item_stocks (modelable_type = App\Models\User) without new tables.
     */
    public function show($colaboradorId)
    {
        $this->authorize('talento.custody.view');

        $colaborador = TalentoColaborador::with('user')->findOrFail($colaboradorId);
        $userId = $colaborador->user_id;

        $stocks = DB::table('inventory_item_stocks as s')
            ->join('inventory_items as i', 'i.id', '=', 's.inventory_item_id')
            ->leftJoin('inventory_categories as c', 'c.id', '=', 'i.category_id')
            ->where('s.modelable_type', 'App\\Models\\User')
            ->where('s.modelable_id', $userId)
            ->whereNull('s.deleted_at')
            ->where('s.current_stock', '>', 0)
            ->select(
                's.id as stock_id',
                'i.id as item_id',
                'i.name as item_name',
                'i.sku',
                'i.unit',
                'c.name as category',
                's.current_stock',
                's.condition',
                's.unit_cost',
                's.created_at as assigned_at'
            )
            ->orderBy('c.name')
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
                'i.sku',
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
