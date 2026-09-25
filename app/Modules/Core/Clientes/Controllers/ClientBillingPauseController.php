<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientBillingPause;
use App\Modules\Core\Clientes\Services\BillingPauseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints de la pausa de facturación programada (Etapa 3 del feature, 2026-09-24).
 * Toda la lógica de negocio vive en BillingPauseService — este controller solo valida
 * entrada, resuelve el modelo por el client_id de la URL (anti-IDOR: una pausa siempre
 * se busca condicionada a `client_id`, nunca solo por su propio id) y traduce el resultado
 * a JSON.
 */
class ClientBillingPauseController extends Controller
{
    public function __construct(private BillingPauseService $service)
    {
    }

    public function estado($id)
    {
        $client = Client::findOrFail($id);
        $elegibilidad = $this->service->puedePausar($client);

        $pausaViva = ClientBillingPause::where('client_id', $id)
            ->whereIn('estado', ClientBillingPause::ESTADOS_ACTIVOS)
            ->latest('id')
            ->first();

        $historial = ClientBillingPause::where('client_id', $id)
            ->with(['creador:id,name,last_name_father', 'canceladoPor:id,name,last_name_father'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'elegibilidad' => $elegibilidad,
            'pausa_viva' => $pausaViva,
            'historial' => $historial,
        ]);
    }

    public function crear(Request $request, $id)
    {
        $data = $request->validate([
            'tipo' => 'required|in:sin_cuota,con_cuota',
            'meses' => 'required|integer|min:1|max:6',
            'motivo' => 'nullable|string|max:255',
            'canal' => 'nullable|in:whatsapp,llamada,oficina',
            'evidencia' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf',
        ]);

        $client = Client::findOrFail($id);

        try {
            $evidenciaPath = null;
            if ($request->hasFile('evidencia')) {
                $evidenciaPath = $request->file('evidencia')->store('private/pausas_facturacion/' . $client->id);
            }

            $pausa = $this->service->crearPausa(
                $client,
                $data['tipo'],
                (int) $data['meses'],
                $data['motivo'] ?? null,
                $data['canal'] ?? null,
                $evidenciaPath
            );

            return response()->json([
                'success' => true,
                'message' => $pausa->estado === ClientBillingPause::ESTADO_ESPERANDO_PAGO
                    ? "Pausa registrada. Queda pendiente el pago de la cuota (\${$pausa->monto_cuota})."
                    : 'Pausa programada correctamente.',
                'pausa' => $pausa,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[ClientBillingPauseController::crear] ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error inesperado.'], 500);
        }
    }

    public function cancelar($id, $pausaId)
    {
        $pausa = ClientBillingPause::where('client_id', $id)->findOrFail($pausaId);

        try {
            $pausa = $this->service->cancelarPausaProgramada($pausa, auth()->user()->id ?? null);

            return response()->json(['success' => true, 'message' => 'Pausa cancelada correctamente.', 'pausa' => $pausa]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[ClientBillingPauseController::cancelar] ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error inesperado.'], 500);
        }
    }

    public function reanudar($id, $pausaId)
    {
        $pausa = ClientBillingPause::where('client_id', $id)->findOrFail($pausaId);

        try {
            $pausa = $this->service->reanudarAnticipada($pausa);

            return response()->json(['success' => true, 'message' => 'Servicio reanudado correctamente.', 'pausa' => $pausa]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[ClientBillingPauseController::reanudar] ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ocurrió un error inesperado.'], 500);
        }
    }
}
