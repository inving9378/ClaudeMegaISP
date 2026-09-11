<?php

namespace App\Modules\Addons\Talento\Support;

use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
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

    /**
     * Item #9990807 — mismo cálculo que TalentoEmployeeDocumentController::resolveEstadoFirma()
     * (que se deja intacta, admin ya aprobado), extraído aquí para que el endpoint self-scoped
     * del Portal de Colaborador (PortalTecnicoController::documentos) construya EXACTAMENTE el
     * mismo estado de firma sin duplicar la regla. $urlBase = prefijo de ruta del consumidor
     * (admin usa /talento/api/colaboradores/{id}/documentos/{docId}/firma, portal usa
     * /talento/portal/documentos/{docId}/firma) para armar signature_url sin acoplar esta clase
     * a ninguna de las dos rutas.
     *
     * @return array{0: array{requiere_firma:bool, firmado:bool, pendiente_firma:bool, signed_at:?string, signature_url:?string}, 1: array}
     */
    public static function describir(TalentoEmployeeDocument $doc, string $urlBase, Collection $firmasDocColeccion): array
    {
        $slots = $doc->template->signatureSlots ?? collect();

        if ($slots->isEmpty()) {
            $firmado = $doc->signed_at !== null;
            $requiereFirma = (bool) ($doc->template->requires_signature ?? false);

            return [[
                'requiere_firma' => $requiereFirma,
                'firmado' => $firmado,
                'pendiente_firma' => $requiereFirma && !$firmado,
                'signed_at' => $doc->signed_at,
                'signature_url' => $firmado ? $urlBase : null,
            ], []];
        }

        $firmasPorSlot = $firmasDocColeccion->keyBy('slot_key');

        $signatureSlots = $slots->map(function ($slot) use ($firmasPorSlot, $urlBase) {
            $firma = $firmasPorSlot->get($slot->key);
            $firmado = $firma?->signed_at !== null;

            return [
                'key' => $slot->key,
                'label' => $slot->label,
                'firmante_tipo' => $slot->firmante_tipo,
                'orden' => $slot->orden,
                'requerido' => $slot->requerido,
                'firmado' => $firmado,
                'signed_at' => $firma?->signed_at,
                'signature_url' => $firmado ? "{$urlBase}?slot_key={$slot->key}" : null,
            ];
        })->values();

        $pendienteFirma = self::pendienteFirma($slots, $firmasPorSlot);

        return [[
            'requiere_firma' => true,
            'firmado' => !$pendienteFirma,
            'pendiente_firma' => $pendienteFirma,
            'signed_at' => $firmasPorSlot->max('signed_at'),
            'signature_url' => null,
        ], $signatureSlots->all()];
    }
}
