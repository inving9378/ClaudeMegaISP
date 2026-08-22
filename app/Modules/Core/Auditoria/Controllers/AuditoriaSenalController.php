<?php

namespace App\Modules\Core\Auditoria\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditoriaSenal;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Listado read-only de señales minadas de la bitácora (item #1016). Sin
 * acciones — solo lectura para revisión humana (clase "producto").
 */
class AuditoriaSenalController extends Controller
{
    public function index(Request $request)
    {
        $tipo = $request->query('tipo');

        $senales = AuditoriaSenal::query()
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->orderByDesc('ocurrido_en')
            ->paginate(25)
            ->withQueryString();

        $causerIds = collect($senales->items())
            ->map(fn($s) => $s->payload['causer_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $causantes = User::whereIn('id', $causerIds)->get()->keyBy('id');

        $tipos = AuditoriaSenal::query()->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');

        return view('core-auditoria::auditoria_senal.index', [
            'senales'   => $senales,
            'causantes' => $causantes,
            'tipos'     => $tipos,
            'tipoActual' => $tipo,
        ]);
    }
}
