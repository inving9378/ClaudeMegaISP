<?php

namespace App\Modules\Addons\Talento\Support;

use Illuminate\Support\Collection;

/**
 * Item #9990655 — punto único de la regla "¿faltan firmas requeridas por slot?", extraída de
 * TalentoEmployeeDocumentController::resolveEstadoFirma() (item #9990649) para que
 * EmployeeDocumentPackageService la consuma también (antes solo miraba 'campo-faltante' en el
 * html, ignorando firmas por completo). Slots vacíos (plantilla sin firma multi-slot) -> false,
 * el llamador sigue su criterio legado (columna signed_at del documento).
 */
class SignatureSlotStatus
{
    /**
     * @param Collection $slots Colección de TalentoDocumentTemplateSignatureSlot (requerido/key).
     * @param Collection $firmasPorSlotKey Colección de TalentoEmployeeDocumentSignature keyBy('slot_key').
     */
    public static function pendienteFirma(Collection $slots, Collection $firmasPorSlotKey): bool
    {
        if ($slots->isEmpty()) {
            return false;
        }

        return $slots->where('requerido', true)
            ->contains(fn ($slot) => !($firmasPorSlotKey->get($slot->key)?->signed_at));
    }
}
