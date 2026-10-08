<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lista o suma pagos de todos los clientes en un periodo (por método, por cliente). Para el detalle
 * de un solo cliente está pagos_cliente; para el corte de un día, resumen_pagos_del_dia.
 */
class ListarPagos extends Herramienta
{
    use FiltrosListado;

    public function nombre(): string
    {
        return 'listar_pagos';
    }

    public function descripcion(): string
    {
        return 'Lista o suma los pagos recibidos de todos los clientes: los últimos pagos, los de un periodo, '
            . 'o por método de pago. Devuelve cuántos son y el total cobrado.';
    }

    public function permiso(): string|array|null
    {
        return 'finance_view_payments';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->propiedadesListado() + [
                'metodo' => ['type' => 'string', 'description' => 'Método de pago (o parte), por ejemplo Efectivo o Transferencia.'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $rango = $this->rango($args);
        if (isset($rango['error'])) {
            return $rango;
        }

        $q = DB::table('payments as p')
            ->leftJoin('method_of_payments as m', 'm.id', '=', 'p.payment_method_id')
            ->leftJoin('client_main_information as c', function ($j) {
                $j->on('c.client_id', '=', 'p.paymentable_id')->where('p.paymentable_type', DatosCliente::TIPO_CLIENTE);
            })
            ->whereNull('p.deleted_at');

        $filtros = [];
        if (($metodo = trim((string) ($args['metodo'] ?? ''))) !== '') {
            $q->where('m.type', 'like', '%' . $metodo . '%');
            $filtros[] = "método «{$metodo}»";
        }
        if ($rango[0]) {
            $q->whereRaw(DatosCliente::FECHA_PAGO_SQL . ' >= ?', [substr($rango[0], 0, 10)]);
        }
        if ($rango[1]) {
            $q->whereRaw(DatosCliente::FECHA_PAGO_SQL . ' <= ?', [substr($rango[1], 0, 10)]);
        }
        if ($p = $this->periodoTexto($rango)) {
            $filtros[] = "pagados {$p}";
        }

        $totales = (clone $q)->selectRaw('COUNT(*) n, COALESCE(SUM(p.amount), 0) total')->first();
        $respuesta = [
            'numero_de_pagos' => (int) $totales->n,
            'total_cobrado'   => DatosCliente::dinero($totales->total),
            'filtros'         => $filtros ?: ['todos los pagos registrados'],
            'enlace_lista'    => $this->enlace('pagos'),
        ];
        if (!empty($args['solo_contar'])) {
            return $respuesta;
        }

        $filas = $q->orderByRaw(DatosCliente::FECHA_PAGO_SQL . ' DESC')->orderByDesc('p.id')
            ->limit($this->cuantos($args))
            ->get(['p.id', 'p.amount', 'p.date', 'm.type as metodo', 'p.paymentable_id', 'p.paymentable_type',
                'c.name', 'c.father_last_name', 'c.mother_last_name']);

        return $respuesta + [
            'mostrando' => $filas->count(),
            'pagos'     => $filas->map(function ($p) {
                $esCliente = $p->paymentable_type === DatosCliente::TIPO_CLIENTE;

                return [
                    'fecha'   => DatosCliente::fecha($p->date),
                    'monto'   => DatosCliente::dinero($p->amount),
                    'metodo'  => $p->metodo ?: 'Sin método',
                    'cliente' => $esCliente ? self::nombreDe($p) . " (núm. {$p->paymentable_id})" : null,
                    'enlace'  => $esCliente ? $this->enlace('ficha_cliente_pagos', $p->paymentable_id) : null,
                ];
            })->all(),
        ];
    }
}
