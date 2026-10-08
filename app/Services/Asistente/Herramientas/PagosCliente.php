<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Estadísticas de pagos de un cliente: totales, promedio, último pago, por método y por mes. */
class PagosCliente extends Herramienta
{
    public function nombre(): string
    {
        return 'pagos_cliente';
    }

    public function descripcion(): string
    {
        return 'Pagos y estadísticas de pago de un cliente: total pagado, número de pagos, promedio, último pago, '
            . 'método que más usa, pagos por mes, meses sin pago y su saldo actual. Por defecto los últimos 12 meses.';
    }

    public function permiso(): string|array|null
    {
        return 'client_billing_view_tab_client';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'numero_cliente' => ['type' => 'integer'],
                'meses'          => ['type' => 'integer', 'description' => 'Cuántos meses hacia atrás (1 a 36). Por defecto 12.'],
            ],
            'required' => ['numero_cliente'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $numero = $this->entero($args, 'numero_cliente');
        $c = $numero ? DatosCliente::cliente($numero) : null;
        if (!$c) {
            return DatosCliente::noEncontrado((int) $numero);
        }

        $meses = max(1, min(36, (int) ($args['meses'] ?? 12)));
        $desde = now()->subMonths($meses - 1)->startOfMonth()->toDateString();
        $fecha = DatosCliente::FECHA_PAGO_SQL;

        $base = DB::table('payments as p')
            ->leftJoin('method_of_payments as m', 'm.id', '=', 'p.payment_method_id')
            ->where('p.paymentable_type', DatosCliente::TIPO_CLIENTE)
            ->where('p.paymentable_id', $numero)
            ->whereNull('p.deleted_at');

        $pagos = (clone $base)->whereRaw("{$fecha} >= ?", [$desde])
            ->orderByRaw("{$fecha} DESC")
            ->get([DB::raw("{$fecha} as fecha"), 'p.amount', 'm.type as metodo']);

        $ultimo = (clone $base)->orderByRaw("{$fecha} DESC")->first([DB::raw("{$fecha} as fecha"), 'p.amount', 'm.type as metodo']);

        $porMetodo = $pagos->groupBy(fn ($p) => $p->metodo ?: 'Sin método')
            ->map(fn ($g, $metodo) => ['metodo' => $metodo, 'pagos' => $g->count(), 'total' => DatosCliente::dinero($g->sum('amount'))])
            ->sortByDesc('pagos')->values();

        $porMes = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $ym = now()->subMonths($i)->format('Y-m');
            $delMes = $pagos->filter(fn ($p) => substr((string) $p->fecha, 0, 7) === $ym);
            $porMes[] = ['mes' => DatosCliente::mes($ym), 'pagos' => $delMes->count(), 'total' => DatosCliente::dinero($delMes->sum('amount'))];
        }

        $total = (float) $pagos->sum('amount');

        return [
            'cliente'          => DatosCliente::nombre($c) . " (núm. {$numero})",
            'periodo'          => "últimos {$meses} meses",
            'total_pagado'     => DatosCliente::dinero($total),
            'numero_de_pagos'  => $pagos->count(),
            'promedio_por_pago' => $pagos->count() ? DatosCliente::dinero($total / $pagos->count()) : null,
            'promedio_mensual' => DatosCliente::dinero($total / $meses),
            'meses_sin_pago'   => collect($porMes)->where('pagos', 0)->count(),
            'ultimo_pago'      => $ultimo ? ['fecha' => DatosCliente::fecha($ultimo->fecha), 'monto' => DatosCliente::dinero($ultimo->amount), 'metodo' => $ultimo->metodo] : null,
            'metodo_mas_usado' => $porMetodo->first()['metodo'] ?? null,
            'por_metodo'       => $porMetodo->all(),
            'por_mes'          => $porMes,
            'ultimos_pagos'    => $pagos->take(5)->map(fn ($p) => ['fecha' => DatosCliente::fecha($p->fecha), 'monto' => DatosCliente::dinero($p->amount), 'metodo' => $p->metodo])->values()->all(),
            'saldo_actual'     => DatosCliente::saldoTexto(DatosCliente::saldo($numero)),
            'enlace'           => $this->enlace('ficha_cliente_pagos', $numero),
        ];
    }
}
