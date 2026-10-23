<?php

namespace App\Modules\Core\Configuracion\Controllers\Finance;

use App\Services\Finance\Billing\InterruptorProformaAnticipada;
use App\Services\Finance\Invoice\EnvioPrefactura;
use App\Services\Finance\Invoice\ProformaAnticipada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-05 (#539): valor general de «Envío prefactura» (días antes del día de facturación; 5 por
 * defecto). Solo escribe billing_config.envio_prefactura_dias (con quién/cuándo, #581): nunca
 * envio_pausado ni el interruptor de la proforma anticipada. Mismo acceso que Configuración ›
 * Notificaciones de finanzas.
 */
class EnvioPrefacturaGeneralController extends Controller
{
    public function __construct(private InterruptorProformaAnticipada $interruptor)
    {
    }

    public function show(): JsonResponse
    {
        $propios = Schema::hasColumn('billing_configurations', 'envio_prefactura_dias')
            ? DB::table('billing_configurations')->whereNotNull('envio_prefactura_dias')->count()
            : 0;

        return response()->json([
            'dias' => EnvioPrefactura::diasPorDefecto(),
            'clientes_con_valor_propio' => $propios,
            'interruptor_encendido' => InterruptorProformaAnticipada::encendida(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate(
            ['dias' => 'required|integer|min:0|max:' . ProformaAnticipada::MAX_DIAS],
            [],
            ['dias' => 'Envío prefactura']
        );
        if (! Schema::hasColumn('billing_config', 'envio_prefactura_dias')) {
            return response()->json(['error' => 'No existe la configuración general de facturación.'], 422);
        }
        // #581: mismo cambio (con auditoría y quién/cuándo) que «Días de anticipo» en Envíos automáticos.
        $this->interruptor->cambiarDias($request->user(), (int) $datos['dias'], 'Notificaciones Finanzas › Facturas proforma');

        return response()->json(['dias' => (int) $datos['dias'], 'message' => 'Envío prefactura guardado.']);
    }
}
