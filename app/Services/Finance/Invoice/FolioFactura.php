<?php

namespace App\Services\Finance\Invoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * #520: folio corto «MN-AAAA-NNNNNN» consecutivo por año. El incremento es atómico en MySQL
 * (UPDATE … LAST_INSERT_ID(ultimo + 1), por conexión): dos facturas creadas a la vez nunca
 * comparten folio. Si la creación de la factura falla después, ese número se salta (puede haber
 * huecos; no se repiten). Nunca impide crear una factura: ante cualquier error devuelve null.
 */
final class FolioFactura
{
    public const PREFIJO = 'MN';

    public static function siguiente(?int $anio = null): ?string
    {
        if (! config('billing.folio_corto', true)) {
            return null;
        }
        $anio ??= (int) now()->format('Y');

        try {
            DB::statement('INSERT IGNORE INTO folio_contadores (anio, ultimo, created_at, updated_at) VALUES (?, 0, NOW(), NOW())', [$anio]);
            DB::update('UPDATE folio_contadores SET ultimo = LAST_INSERT_ID(ultimo + 1), updated_at = NOW() WHERE anio = ?', [$anio]);
            $n = (int) DB::selectOne('SELECT LAST_INSERT_ID() AS n')->n;

            return $n > 0 ? self::formato($anio, $n) : null;
        } catch (\Throwable $e) {
            Log::warning('[FolioFactura] sin folio (la factura se crea igual): ' . $e->getMessage());

            return null;
        }
    }

    public static function formato(int $anio, int $n): string
    {
        return sprintf('%s-%d-%06d', self::PREFIJO, $anio, $n);
    }
}
