<?php

namespace App\Modules\Addons\FirmaElectronica\Contracts;

use App\Modules\Addons\FirmaElectronica\Models\FirmaSolicitud;

/** Contrato independiente del proveedor (patrón OltDriverInterface / WhatsAppProviderInterface). */
interface FirmaProviderInterface
{
    public function nombre(): string;

    /** Crea la solicitud en el proveedor y devuelve su id externo. */
    public function crearSolicitud(FirmaSolicitud $solicitud): string;

    /** Estado según el proveedor (verificación fuera de banda). */
    public function consultarEstado(string $idExterno): string;

    public function cancelar(string $idExterno): bool;

    /** Ruta/contenido del resultado firmado, o null si aún no existe. */
    public function descargarResultado(string $idExterno): ?string;

    /** Verifica la autenticidad del webhook (comparación en tiempo constante). */
    public function verificarWebhook(string $cuerpo, ?string $firma): bool;
}
