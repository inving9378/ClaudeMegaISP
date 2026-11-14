<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

/**
 * Reparto del presupuesto de una Campaña 360 entre sus canales (§2.5.4). Dinero en MXN, siempre cadenas con 2
 * decimales y cálculos con bcmath (nunca float).
 *
 * - Solo quedan los canales de la campaña (uno por canal, el primero gana).
 * - Porcentajes que no suman 100.00 se normalizan (proporcionales; todos en 0 → partes iguales).
 * - `monto_mxn` = presupuesto × porcentaje / 100 redondeado half-up a centavos; el ÚLTIMO renglón absorbe la
 *   diferencia, así la suma es exactamente el presupuesto. Presupuesto 0 → todos "0.00".
 * - Si el renglón final quedara negativo (caso de centavos sueltos), se reparte por residuos mayores, que también
 *   suma exacto y nunca da negativos.
 */
class RepartoPresupuesto
{
    private const ESCALA = 8;

    /**
     * @param  array<int,mixed>  $renglones  [{canal, porcentaje, …}]
     * @param  array<int,string>  $canales  canales de la campaña
     * @return array<int,array{canal:string,porcentaje:string,monto_mxn:string}>
     */
    public function calcular(array $renglones, array $canales, string $presupuesto): array
    {
        $presupuesto = self::dinero($presupuesto);
        $vistos = [];
        $limpios = [];

        foreach ($renglones as $r) {
            if (! is_array($r)) {
                continue;
            }
            $canal = $r['canal'] ?? null;
            if (! is_string($canal) || ! in_array($canal, $canales, true) || isset($vistos[$canal])) {
                continue;
            }
            $vistos[$canal] = true;
            $limpios[] = ['canal' => $canal, 'porcentaje' => self::porcentajeCrudo($r['porcentaje'] ?? '0')];
        }

        if ($limpios === []) {
            return [];
        }

        $porcentajes = $this->repartir(array_column($limpios, 'porcentaje'), '100.00');
        $montos      = bccomp($presupuesto, '0', 2) === 0
            ? array_fill(0, count($limpios), '0.00')
            : $this->repartir($porcentajes, $presupuesto);

        $salida = [];
        foreach ($limpios as $i => $r) {
            $salida[] = ['canal' => $r['canal'], 'porcentaje' => $porcentajes[$i], 'monto_mxn' => $montos[$i]];
        }

        return $salida;
    }

    /**
     * Reparte $total (2 decimales) en proporción a $pesos. Suma exacta.
     *
     * @param  array<int,string>  $pesos
     * @return array<int,string>
     */
    public function repartir(array $pesos, string $total): array
    {
        $n = count($pesos);
        $suma = '0';
        foreach ($pesos as $p) {
            $suma = bcadd($suma, $p, self::ESCALA);
        }

        if (bccomp($suma, '0', self::ESCALA) <= 0) {
            $pesos = array_fill(0, $n, '1');
            $suma  = (string) $n;
        }

        $partes = [];
        $acumulado = '0.00';
        for ($i = 0; $i < $n - 1; $i++) {
            $exacto = bcdiv(bcmul($total, $pesos[$i], self::ESCALA), $suma, self::ESCALA);
            $partes[$i] = self::redondear($exacto);
            $acumulado = bcadd($acumulado, $partes[$i], 2);
        }
        $partes[$n - 1] = bcsub($total, $acumulado, 2);

        if (bccomp($partes[$n - 1], '0', 2) >= 0) {
            return $partes;
        }

        return $this->porResiduosMayores($pesos, $suma, $total);
    }

    /** Método de residuos mayores (respaldo): pisos a centavos y los centavos que faltan a los residuos más altos. */
    private function porResiduosMayores(array $pesos, string $suma, string $total): array
    {
        $centavosTotal = bcmul($total, '100', 0);
        $pisos = [];
        $residuos = [];
        $asignados = '0';

        foreach ($pesos as $i => $p) {
            $exacto = bcdiv(bcmul($centavosTotal, $p, self::ESCALA), $suma, self::ESCALA);
            $pisos[$i] = bcadd($exacto, '0', 0);
            $residuos[$i] = bcsub($exacto, $pisos[$i], self::ESCALA);
            $asignados = bcadd($asignados, $pisos[$i], 0);
        }

        $faltan = (int) bcsub($centavosTotal, $asignados, 0);
        $orden = array_keys($residuos);
        usort($orden, fn ($a, $b) => bccomp($residuos[$b], $residuos[$a], self::ESCALA) ?: $b <=> $a);

        foreach (array_slice($orden, 0, $faltan) as $i) {
            $pisos[$i] = bcadd($pisos[$i], '1', 0);
        }

        return array_map(fn ($c) => bcdiv($c, '100', 2), $pisos);
    }

    /** Half-up a 2 decimales de un número no negativo en cadena. */
    public static function redondear(string $valor): string
    {
        return bcadd($valor, '0.005', 2);
    }

    /** Normaliza una cantidad de dinero a cadena con 2 decimales (half-up). Lo no numérico → "0.00". */
    public static function dinero(mixed $valor): string
    {
        $s = is_int($valor) ? (string) $valor : (is_string($valor) ? trim($valor) : null);
        if ($s === null && is_float($valor)) {
            $s = number_format($valor, 4, '.', '');
        }
        if ($s === null || ! preg_match('/^\d+(\.\d+)?$/', $s)) {
            return '0.00';
        }

        return self::redondear($s);
    }

    private static function porcentajeCrudo(mixed $valor): string
    {
        $s = self::dinero($valor);

        return bccomp($s, '100', 2) > 0 ? '100.00' : $s;
    }
}
