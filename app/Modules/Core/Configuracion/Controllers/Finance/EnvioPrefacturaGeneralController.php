<?php

namespace App\Modules\Core\Configuracion\Controllers\Finance;

use App\Services\Finance\Invoice\EnvioPrefactura;
use App\Services\Finance\Invoice\ProformaAnticipada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-05 (#539): valor general de «Envío prefactura» (días antes del día de facturación; 5 por
 * defecto). Solo lee y escribe billing_config.envio_prefactura_dias: nunca envio_pausado ni ninguna
 * otra columna. Mismo acceso que Configuración › Notificaciones de finanzas.
 */
class EnvioPrefacturaGeneralController extends Controller
{
    public function show(): JsonResponse
    {
        $propios = Schema::hasColumn('billing_configurations', 'envio_prefactura_dias')
            ? DB::table('billing_configurations')->whereNotNull('envio_prefactura_dias')->count()
            : 0;

        return response()->json([
            'dias' => EnvioPrefactura::diasPorDefecto(),
            'clientes_con_valor_propio' => $propios,
            'interruptor_encendido' => (bool) config('billing.proforma_anticipada'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate(
            ['dias' => 'required|integer|min:0|max:' . ProformaAnticipada::MAX_DIAS],
            [],
            ['dias' => 'Envío prefactura']
        );
        $id = DB::table('billing_config')->orderBy('id')->value('id');
        if (! $id || ! Schema::hasColumn('billing_config', 'envio_prefactura_dias')) {
            return response()->json(['error' => 'No existe la configuración general de facturación.'], 422);
        }
        DB::table('billing_config')->where('id', $id)->update(['envio_prefactura_dias' => (int) $datos['dias'], 'updated_at' => now()]);

        return response()->json(['dias' => (int) $datos['dias'], 'message' => 'Envío prefactura guardado.']);
    }
}
