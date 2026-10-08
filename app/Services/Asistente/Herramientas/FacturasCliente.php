<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Últimas facturas de un cliente y cuánto tiene pendiente. */
class FacturasCliente extends Herramienta
{
    public const ESTADOS = [
        'Pagado'                         => 'Pagada',
        'Atrasado'                       => 'Atrasada',
        'Partially paid'                 => 'Pagada en parte',
        'impagado'                       => 'Sin pagar',
        'Pagar (del saldo de la cuenta)' => 'Se cobra del saldo de la cuenta',
    ];

    public const PENDIENTES = ['Atrasado', 'Partially paid', 'impagado'];

    public function nombre(): string
    {
        return 'facturas_cliente';
    }

    public function descripcion(): string
    {
        return 'Últimas facturas de un cliente (número, total, estado, emisión y vencimiento) y el total de facturas pendientes.';
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
                'cuantas'        => ['type' => 'integer', 'description' => 'Cuántas facturas recientes mostrar (1 a 24). Por defecto 6.'],
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

        $cuantas = max(1, min(24, (int) ($args['cuantas'] ?? 6)));
        $base = DB::table('client_invoices')->where('client_id', $numero)->where(fn ($w) => $w->where('is_proforma', 0)->orWhereNull('is_proforma'));

        $pendientes = (clone $base)->whereIn('estado', self::PENDIENTES)->get(['total']);

        return [
            'cliente'               => DatosCliente::nombre($c) . " (núm. {$numero})",
            'saldo_actual'          => DatosCliente::saldoTexto(DatosCliente::saldo($numero)),
            'facturas_atrasadas_o_sin_pagar' => $pendientes->count(),
            'total_atrasado_o_sin_pagar'     => DatosCliente::dinero($pendientes->sum('total')),
            'como_leerlo'           => 'Las facturas que "se cobran del saldo de la cuenta" se cargan a su saldo: lo que el cliente debe HOY es su saldo actual, no la suma de facturas.',
            'ultimas_facturas'    => (clone $base)->orderByDesc('id')->limit($cuantas)->get(['number', 'total', 'estado', 'created_at', 'pay_up'])
                ->map(fn ($f) => [
                    'factura' => (string) $f->number,
                    'total'   => DatosCliente::dinero($f->total),
                    'estado'  => self::ESTADOS[$f->estado] ?? $f->estado,
                    'emitida' => DatosCliente::fecha($f->created_at),
                    'vence'   => DatosCliente::fecha($f->pay_up),
                ])->all(),
            'enlace' => $this->enlace('ficha_cliente_pagos', $numero),
        ];
    }
}
