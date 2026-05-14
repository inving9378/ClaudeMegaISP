<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reusa la tabla `tickets` de MegaISP filtrando por una categoría/etiqueta
 * "MegaFamilia". Si en el futuro se decide separar, crear `parental_tickets`.
 */
class SoporteController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::soporte.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = Ticket::query()
            ->where(function ($qq) {
                $qq->where('title', 'like', '%MegaFamilia%')
                   ->orWhere('description', 'like', '%MegaFamilia%');
            })
            ->when($request->estado, fn ($qq, $v) => $qq->where('estado', $v))
            ->orderByDesc('id');
        return response()->json($q->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Ticket::with('thread')->findOrFail($id));
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);
        $t = Ticket::findOrFail($id);
        // Implementación específica delegada al módulo Tickets — placeholder:
        return response()->json([
            'success' => true,
            'note' => 'Respuesta delegada al módulo Tickets. Integrar TicketThreadController@store cuando se exponga.',
        ]);
    }
}
