<?php

namespace App\Modules\Addons\Payments\Services\Identification;

use Illuminate\Support\Facades\DB;

/**
 * FASE 3 (F3.2) — Resuelve una referencia de pago MEG a su cliente real.
 *
 * Camino EXACTO de identificación: si el concepto del comprobante trae una
 * referencia "MEG-XXXXXXXX-XX", se busca en client_payment_references y se
 * devuelve el cliente. Es la única vía que F4 podrá auto-aplicar (certeza EXACT).
 *
 * Read-only: no aplica pago, no escribe nada.
 */
class MegReferenceResolver
{
    /**
     * Extrae la referencia MEG canónica de un texto (o null si no hay).
     * Tolera minúsculas, espacios en vez de guiones y dígitos sin ceros a la
     * izquierda ("MEG-17-47" → "MEG-00000017-47"). La verificación real es la
     * existencia en la tabla, así que padear no puede inventar un cliente.
     */
    public function extractReference(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        $up = strtoupper($text);
        if (!preg_match('/MEG[-\s]*(\d{1,8})[-\s]*(\d{2})/', $up, $m)) {
            return null;
        }

        $idPart = str_pad($m[1], 8, '0', STR_PAD_LEFT);
        return 'MEG-' . $idPart . '-' . $m[2];
    }

    /**
     * Resuelve el texto (concepto) a un cliente vía la referencia MEG.
     *
     * @return array|null ['client_id' => int, 'reference' => string] o null.
     */
    public function resolveFromText(?string $text): ?array
    {
        $ref = $this->extractReference($text);
        if ($ref === null) {
            return null;
        }

        $row = DB::table('client_payment_references')
            ->where('reference', $ref)
            ->first(['client_id', 'reference']);

        if (!$row) {
            return null; // ref con formato válido pero no existe → no inventamos.
        }

        return ['client_id' => (int) $row->client_id, 'reference' => $row->reference];
    }
}
