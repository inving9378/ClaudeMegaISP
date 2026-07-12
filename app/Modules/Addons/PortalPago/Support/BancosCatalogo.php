<?php

namespace App\Modules\Addons\PortalPago\Support;

use Illuminate\Support\Str;

/**
 * Catálogo de bancos comunes en México para el select del reporte de pago.
 *
 * El CEP de Banxico requiere la CLAVE del participante (3-5 dígitos), no el
 * nombre. `claveBanxico()` mapea los bancos con clave verificada; las
 * fintechs con clave variable (Nu, Hey Banco, Klar, Mercado Pago) y "Otro"
 * quedan A PROPÓSITO sin mapear — adivinar mal una clave en el flujo de
 * conciliación de pagos es peor que no mapear (Hoja de Ruta #161).
 */
class BancosCatalogo
{
    public static function comunes(): array
    {
        return [
            'BBVA',
            'Citibanamex',
            'Santander',
            'Banorte',
            'HSBC',
            'Scotiabank',
            'Inbursa',
            'Banco Azteca',
            'BanCoppel',
            'Banregio',
            'Afirme',
            'Banjercito',
            'Mifel',
            'Bancrea',
            'STP',
            'Nu',
            'Hey Banco',
            'Klar',
            'Mercado Pago',
            'Otro',
        ];
    }

    /**
     * Clave de 3 dígitos del participante Banxico para el nombre dado, o null
     * si no está en el catálogo verificado (match exacto tras normalizar
     * acentos/mayúsculas — nunca adivina por coincidencia parcial).
     */
    public static function claveBanxico(?string $nombre): ?string
    {
        if ($nombre === null || trim($nombre) === '') {
            return null;
        }

        return self::claves()[self::normalizar($nombre)] ?? null;
    }

    private static function normalizar(string $nombre): string
    {
        return Str::of($nombre)->trim()->ascii()->lower()->value();
    }

    /** @return array<string,string> nombre normalizado => clave Banxico */
    private static function claves(): array
    {
        return [
            'bbva'          => '012',
            'citibanamex'   => '002',
            'santander'     => '014',
            'banorte'       => '072',
            'hsbc'          => '021',
            'scotiabank'    => '044',
            'inbursa'       => '036',
            'banco azteca'  => '127',
            'bancoppel'     => '137',
            'banregio'      => '058',
            'afirme'        => '062',
            'banjercito'    => '019',
            'mifel'         => '042',
            'bancrea'       => '152',
            'stp'           => '646',
        ];
    }
}
