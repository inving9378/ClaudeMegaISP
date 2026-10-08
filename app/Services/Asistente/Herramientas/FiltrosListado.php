<?php

namespace App\Services\Asistente\Herramientas;

use Carbon\Carbon;

/**
 * Filtros que comparten los listados (clientes, prospectos, tickets, pagos): cuántos traer y
 * rango de fechas. Los argumentos tienen los mismos nombres en todas las herramientas para que
 * la IA no tenga que aprender uno distinto por listado.
 */
trait FiltrosListado
{
    protected function propiedadesListado(): array
    {
        return [
            'cuantos'     => ['type' => 'integer', 'description' => 'Cuántos mostrar (1 a 20). Por defecto 5.'],
            'desde'       => ['type' => 'string', 'description' => 'Fecha inicial AAAA-MM-DD (incluida).'],
            'hasta'       => ['type' => 'string', 'description' => 'Fecha final AAAA-MM-DD (incluida).'],
            'solo_contar' => ['type' => 'boolean', 'description' => 'true = solo decir cuántos hay, sin listar.'],
        ];
    }

    protected function cuantos(array $args, int $porDefecto = 5): int
    {
        $n = (int) ($args['cuantos'] ?? 0);

        return $n < 1 ? $porDefecto : min($n, 20);
    }

    /**
     * Rango de fechas inclusivo como ['Y-m-d 00:00:00', 'Y-m-d 23:59:59']; null en un extremo = abierto.
     *
     * @return array{0:?string,1:?string}|array{error:string}
     */
    protected function rango(array $args): array
    {
        $out = [null, null];
        foreach (['desde' => 0, 'hasta' => 1] as $clave => $i) {
            $v = trim((string) ($args[$clave] ?? ''));
            if ($v === '') {
                continue;
            }
            try {
                $f = Carbon::parse($v);
            } catch (\Throwable) {
                return ['error' => "No entendí la fecha «{$v}». Usa año-mes-día, por ejemplo 2026-10-07."];
            }
            $out[$i] = $i === 0 ? $f->startOfDay()->toDateTimeString() : $f->endOfDay()->toDateTimeString();
        }

        return $out;
    }

    /** "del 1 de octubre de 2026 al 7 de octubre de 2026", para que la IA repita el periodo. */
    protected function periodoTexto(array $rango): ?string
    {
        [$desde, $hasta] = $rango;
        if (!$desde && !$hasta) {
            return null;
        }

        return trim(($desde ? 'del ' . DatosCliente::fecha($desde) : '') . ($hasta ? ' al ' . DatosCliente::fecha($hasta) : ' a hoy'));
    }

    protected static function nombreDe(object $r, string $prefijo = ''): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $r->{$prefijo . 'name'} ?? '', $r->{$prefijo . 'father_last_name'} ?? '', $r->{$prefijo . 'mother_last_name'} ?? '',
        ])));
    }
}
