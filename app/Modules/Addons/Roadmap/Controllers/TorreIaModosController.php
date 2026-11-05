<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Services\IaModosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** #1115 — pantalla «Modelos de IA» (Torre → Configuración). Ver: torre.config.view; editar: solo super-administrator. */
class TorreIaModosController extends Controller
{
    public function __construct(private IaModosService $ia)
    {
    }

    private function ver(): void
    {
        $this->authorize('torre.config.view');
    }

    private function editar(Request $r): void
    {
        $this->authorize('torre.config.edit');
        abort_unless($r->user()?->hasRole('super-administrator'), 403, 'Solo super-administrator cambia los modelos de IA.');
        abort_unless($this->ia->disponible(), 503, 'Falta correr la migración de modelos de IA.');
    }

    public function index(): JsonResponse
    {
        $this->ver();

        return response()->json($this->ia->disponible() ? $this->ia->estado() : ['modo' => 'ahorro', 'no_disponible' => true]);
    }

    public function modo(Request $r): JsonResponse
    {
        $this->editar($r);
        $this->ia->fijarModo((string) $r->input('modo'), $r->user());

        return response()->json($this->ia->estado());
    }

    public function potente(Request $r): JsonResponse
    {
        $this->editar($r);
        $this->ia->potenteDosHoras($r->user());

        return response()->json($this->ia->estado());
    }

    public function pieza(Request $r): JsonResponse
    {
        $this->editar($r);
        $this->ia->fijarPieza((string) $r->input('modo'), (string) $r->input('pieza'), (string) $r->input('modelo'), $r->user());

        return response()->json($this->ia->estado());
    }

    public function verificar(Request $r): JsonResponse
    {
        $this->editar($r);
        $res = $this->ia->verificar((string) $r->input('modelo'), $r->user());

        return response()->json(['resultado' => $res] + $this->ia->estado());
    }
}
