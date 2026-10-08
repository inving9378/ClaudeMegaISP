<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Lo cobrado en un día: total, número de pagos y por método. */
class ResumenPagosDelDia extends Herramienta
{
    public function nombre(): string
    {
        return 'resumen_pagos_del_dia';
    }

    public function descripcion(): string
    {
        return 'Cuánto se cobró en un día (por defecto hoy): total, número de pagos y desglose por método de pago.';
    }

    public function permiso(): string|array|null
    {
        return 'finance_view_payments';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['fecha' => ['type' => 'string', 'description' => 'Día a consultar en formato AAAA-MM-DD. Por defecto hoy.']],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        try {
            $dia = !empty($args['fecha']) ? Carbon::parse((string) $args['fecha'])->toDateString() : today()->toDateString();
        } catch (\Throwable) {
            return ['error' => 'No entendí la fecha. Usa el formato año-mes-día, por ejemplo 2026-10-07.'];
        }

        $pagos = DB::table('payments as p')
            ->leftJoin('method_of_payments as m', 'm.id', '=', 'p.payment_method_id')
            ->whereNull('p.deleted_at')
            ->whereRaw(DatosCliente::FECHA_PAGO_SQL . ' = ?', [$dia])
            ->get(['p.amount', 'm.type as metodo']);

        return [
            'dia'             => DatosCliente::fecha($dia),
            'total_cobrado'   => DatosCliente::dinero($pagos->sum('amount')),
            'numero_de_pagos' => $pagos->count(),
            'por_metodo'      => $pagos->groupBy(fn ($p) => $p->metodo ?: 'Sin método')
                ->map(fn ($g, $m) => ['metodo' => $m, 'pagos' => $g->count(), 'total' => DatosCliente::dinero($g->sum('amount'))])
                ->sortByDesc('pagos')->values()->all(),
            'enlace' => $this->enlace('pagos'),
        ];
    }
}
