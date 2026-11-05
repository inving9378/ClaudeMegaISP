<?php

namespace App\Services\Clientes;

/**
 * «Tipo de cliente» (item #988): la ÚNICA fuente de verdad de qué clase de cliente es. La lógica del
 * sistema se basa en este tipo, nunca en quién es el cliente. is_dedicated se deriva de él y Categoría
 * ya no se captura; quien todavía necesite esa palabra usa categoriaDerivada().
 */
final class TipoCliente
{
    public const RESIDENCIAL = 'residencial';
    public const NEGOCIO = 'negocio';
    public const PYME = 'pyme';
    public const DEDICADO = 'dedicado';

    public const OPCIONES = [
        self::RESIDENCIAL => 'Residencial',
        self::NEGOCIO     => 'Negocio',
        self::PYME        => 'Pyme',
        self::DEDICADO    => 'Dedicado',
    ];

    /** Sin tipo, o con uno que no existe: Residencial. Nunca null ni error. */
    public static function normalizar(mixed $tipo): string
    {
        return is_string($tipo) && isset(self::OPCIONES[$tipo]) ? $tipo : self::RESIDENCIAL;
    }

    public static function esDedicado(mixed $tipo): bool
    {
        return $tipo === self::DEDICADO;
    }

    /** La categoría vieja, para plantillas o reportes que aún la pidan: Residencial → Particular, el resto → Empresa. */
    public static function categoriaDerivada(mixed $tipo): string
    {
        return self::normalizar($tipo) === self::RESIDENCIAL ? 'Particular' : 'Empresa';
    }

    /** Para los caminos que insertan directo, sin pasar por el modelo. */
    public static function aplicarEnDatos(array $datos): array
    {
        $datos['tipo_cliente'] = array_key_exists('tipo_cliente', $datos)
            ? self::normalizar($datos['tipo_cliente'])
            : (! empty($datos['is_dedicated']) ? self::DEDICADO : self::RESIDENCIAL);
        $datos['is_dedicated'] = self::esDedicado($datos['tipo_cliente']);

        return $datos;
    }
}
