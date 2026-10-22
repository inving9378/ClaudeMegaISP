<?php

namespace App\Modules\Addons\Finanzas\Controllers\Correcciones;

use App\Models\FacturacionCorreccion as Correccion;
use App\Services\Finance\Billing\CorreccionesFacturacion;
use App\Support\ArchivoObligatorioException;
use App\Support\Seguridad\SuperAdministrador;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Finanzas › «Correcciones pendientes» (item #571, Parte B): lo que la corrida automática no
 * aplicó sola, con su antes y después, para aprobar o descartar. Solo super-administrador; cada
 * decisión queda en activity_log (facturacion-correcciones). La lógica vive en CorreccionesFacturacion.
 */
class CorreccionesController extends Controller
{
    public function __construct(private CorreccionesFacturacion $correcciones)
    {
    }

    public function index()
    {
        return view('addon-finanzas::correcciones.index', ['puedeGestionar' => SuperAdministrador::es(auth()->user())]);
    }

    public function listar(Request $request): JsonResponse
    {
        $this->exigirPermiso();
        $pendientes = $request->query('vista', 'pendientes') === 'pendientes';

        $filas = Correccion::query()
            ->when($pendientes, fn ($q) => $q->where('estado', Correccion::PENDIENTE), fn ($q) => $q->where('estado', '!=', Correccion::PENDIENTE))
            ->orderByRaw("FIELD(tipo, 'monto_pago', 'reacomodo', 'revision')")->orderByDesc('updated_at')
            ->limit($pendientes ? 500 : 200)
            ->get();
        $nombres = DB::table('client_main_information')->whereIn('client_id', $filas->pluck('client_id')->unique())
            ->get(['client_id', 'name', 'father_last_name', 'mother_last_name'])
            ->mapWithKeys(fn ($c) => [$c->client_id => trim("{$c->name} {$c->father_last_name} {$c->mother_last_name}")]);
        $usuarios = DB::table('users')->whereIn('id', $filas->pluck('resuelta_por')->filter()->unique())
            ->get(['id', 'name', 'father_last_name'])->mapWithKeys(fn ($u) => [$u->id => trim("{$u->name} {$u->father_last_name}")]);

        return response()->json([
            'correcciones' => $filas->map(fn (Correccion $c) => $c->only([
                'id', 'client_id', 'tipo', 'origen', 'categoria', 'estado', 'parametros', 'antes', 'despues', 'deuda_antes', 'deuda_despues', 'detalle', 'motivo', 'respaldo_path',
            ]) + [
                'cliente' => $nombres[$c->client_id] ?? '',
                'resuelta_por' => $c->resuelta_por ? ($usuarios[$c->resuelta_por] ?? "usuario {$c->resuelta_por}") : ($c->estado === Correccion::APLICADA ? 'Automática (al actualizar)' : null),
                'resuelta_at' => $c->resuelta_at?->toIso8601String(),
                'creada_at' => $c->created_at?->toIso8601String(),
                'tiene_deshacer' => (bool) $c->deshacer_sql,
            ])->values(),
            'conteo' => Correccion::selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado'),
            'ultima_corrida' => DB::table('facturacion_corridas')->orderByDesc('id')->first(['id', 'descripcion', 'estado', 'iniciada_at', 'terminada_at', 'resumen', 'error']),
        ]);
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate(['monto' => 'nullable|numeric|min:0']);

        return $this->responder(fn () => $this->correcciones->aprobar(Correccion::findOrFail($id), $request->user(), isset($datos['monto']) ? (float) $datos['monto'] : null), 'Corrección aplicada.');
    }

    public function descartar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso();
        $datos = $request->validate(['motivo' => 'required|string|min:5|max:500'], ['motivo.required' => 'Escribe por qué se descarta.', 'motivo.min' => 'Escribe por qué se descarta.']);

        return $this->responder(fn () => $this->correcciones->descartar(Correccion::findOrFail($id), $request->user(), $datos['motivo']), 'Corrección descartada.');
    }

    private function responder(callable $accion, string $mensaje): JsonResponse
    {
        try {
            $c = $accion();

            return response()->json(['message' => $mensaje, 'estado' => $c->estado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ArchivoObligatorioException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function exigirPermiso(): void
    {
        if (! SuperAdministrador::es(auth()->user())) {
            throw new AuthorizationException('Solo un super-administrador puede ver y resolver las correcciones de facturación.');
        }
    }
}
