<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Models\ClientHistorialCambio;
use App\Services\Clientes\HistorialCliente;
use App\Services\Finance\Billing\CapaContrato;
use App\Services\Finance\Billing\ReglasFacturacion;
use App\Support\PlazoContrato;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * FR-07 (#541): contrato del cliente en la ficha — plazo, inicio, fin, estado (vigente / cumplido /
 * renovado), facturas sin pago tras cumplirse y la autorrenovación (la «Renovación automática» de su
 * paquete). Ver: client_view_client; cambiar la autorrenovación: client_edit_client.
 */
class ClientContratoController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $info = DB::table('client_main_information')->where('client_id', $id)->first();
        abort_if(! $info, 404);
        $auto = CapaContrato::autorrenovacion($id);
        $c = PlazoContrato::estadoEn($info, Carbon::today(), $auto);
        $especial = PlazoContrato::esEspecial($info->duration_contract_id);
        $nombre = $info->duration_contract_id
            ? (string) DB::table('duration_contracts')->where('id', $info->duration_contract_id)->value('name')
            : '';
        $desde = ReglasFacturacion::reglasDesde();

        return response()->json([
            'plazo' => $nombre ? ($especial ? "{$nombre} (" . PlazoContrato::meses($info) . ' meses)' : $nombre) : 'Sin plazo (cuenta como 18 meses)',
            'meses' => PlazoContrato::meses($info),
            'inicio' => $c ? $c['inicio']->format('d/m/Y') : null,
            'fin' => $c ? $c['fin']->format('d/m/Y') : null,
            'estado' => $c['estado'] ?? null,
            'plazo_numero' => $c['plazo'] ?? null,
            'inicio_por_alta' => PlazoContrato::fecha($info->activation_date) === null,
            'autorrenovacion' => $auto,
            'tiene_paquete' => CapaContrato::tienePaquete($id),
            'facturas_sin_pago' => CapaContrato::cargosSinPago($id),
            'facturas_max_sin_pago' => CapaContrato::facturasMaxSinPago($id),
            'capa_encendida' => CapaContrato::encendida(),
            'reglas_desde' => $desde ? $desde->format('d/m/Y') : null,
        ]);
    }

    public function autorrenovacion(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['autorrenovacion' => 'required|boolean']);
        if (! CapaContrato::tienePaquete($id)) {
            return response()->json(['error' => 'El cliente no tiene paquete: la autorrenovación vive en la «Renovación automática» de su paquete.'], 422);
        }
        $antes = CapaContrato::autorrenovacion($id);
        $nuevo = (bool) $datos['autorrenovacion'];
        DB::table('client_bundle_services')->where('client_id', $id)->update(['automatic_renewal' => $nuevo, 'updated_at' => now()]);
        HistorialCliente::registrar($id, 'autorrenovacion', $antes ? 'Sí' : 'No', $nuevo ? 'Sí' : 'No', ClientHistorialCambio::ORIGEN_USUARIO);

        return response()->json(['autorrenovacion' => $nuevo, 'message' => 'Autorrenovación ' . ($nuevo ? 'activada' : 'desactivada') . '.']);
    }
}
