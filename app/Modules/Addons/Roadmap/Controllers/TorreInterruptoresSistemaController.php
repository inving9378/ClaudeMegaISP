<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * #1070 — Lista de SOLO LECTURA de los interruptores del sistema (unificación + los de dinero de cada
 * módulo) con su estado vigente y qué hacen. No escribe nada: cambiarlos sigue siendo editar el .env.
 */
class TorreInterruptoresSistemaController extends Controller
{
    /** Interruptores de dinero: [grupo, clave de config, .env, qué hace]. */
    private const DINERO = [
        ['Domiciliación', 'domiciliacion.cobro_live_enabled', 'DOMICILIACION_COBRO_LIVE_ENABLED', 'Permite que `domiciliacion:cobrar` cobre de verdad a los clientes. Apagado = no se cobra.'],
        ['Flotas', 'flotas_billing.billing_enabled', 'FLOTAS_BILLING_ENABLED', 'Mete el cobro de Flotas dentro de la factura. Apagado = la factura sale como siempre.'],
        ['Pagos', 'pagos.recurrentes_cron_enabled', 'PAGOS_RECURRENTES_CRON_ENABLED', 'Programa el envío diario de ligas de pago recurrentes.'],
        ['Pagos', 'pagos.medussa_fees_enabled', 'PAGOS_MEDUSSA_FEES_ENABLED', 'Activa el cálculo de comisiones Medussa en pagos.'],
        ['Conciliación', 'payments.auto_apply_enabled', 'PAYMENTS_AUTO_APPLY_ENABLED', 'Freno maestro: permite aplicar pagos identificados sin que los confirme una persona.'],
    ];

    public function __invoke(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $items = [];
        foreach (self::DINERO as [$grupo, $clave, $env, $que]) {
            $items[] = [
                'grupo' => $grupo, 'clave' => $clave, 'env' => $env, 'que_hace' => $que,
                'dinero' => true, 'encendido' => (bool) config($clave),
            ];
        }

        $descripciones = $this->descripciones();
        foreach ((array) config('unificacion.interruptores', []) as $clave => $valor) {
            if (!is_bool($valor)) {
                continue; // parámetros numéricos (p. ej. meses de garantía), no son interruptores
            }
            $items[] = [
                'grupo' => 'Unificación', 'clave' => "unificacion.interruptores.$clave",
                'env' => 'UNIFICACION_' . strtoupper($clave),
                'que_hace' => $descripciones[$clave] ?? 'Cambio del proyecto de unificación; apagado = comportamiento de hoy.',
                'dinero' => false, 'encendido' => $valor,
            ];
        }

        return response()->json(['ok' => true, 'interruptores' => $items]);
    }

    /** Saca de los config el bloque de comentarios `//` que precede a cada clave. */
    private function descripciones(): array
    {
        $archivos = array_merge([config_path('unificacion.php')], glob(config_path('unificacion.d/*.php')) ?: []);
        $out = [];
        foreach ($archivos as $archivo) {
            $buffer = [];
            foreach (file($archivo) ?: [] as $linea) {
                $t = trim($linea);
                if (str_starts_with($t, '//')) {
                    $buffer[] = trim(substr($t, 2));
                    continue;
                }
                if (preg_match("/^'([a-z0-9_]+)'\s*=>/", $t, $m) && $buffer) {
                    $out[$m[1]] ??= trim(implode(' ', $buffer));
                }
                if ($t !== '') {
                    $buffer = [];
                }
            }
        }

        return $out;
    }
}
