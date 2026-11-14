<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Modules\Addons\IA\Services\IA;

/**
 * Única puerta de las Campañas 360 hacia la IA: `IA::configurada` / `IA::enviar` del módulo IA central (claves
 * `marketing.campana_360` y `marketing.piezas_campana`). Sin clientes HTTP propios ni llaves. Existe como clase
 * aparte solo para que las pruebas puedan sustituirla en el contenedor (ninguna llamada real a la IA).
 *
 * `IA::enviar` lanza `IANoConfigurada` si la función no tiene IA asignada; los generadores la dejan subir.
 */
class ClienteIACampana
{
    public const ESTRATEGIA = 'marketing.campana_360';

    public const PIEZAS = 'marketing.piezas_campana';

    /** Segundos por llamada a la IA (2 × 75 = 150 < 170 del trabajo). */
    public const TIMEOUT_LLAMADA = 75;

    public function configurada(string $clave): bool
    {
        return IA::configurada($clave);
    }

    /**
     * @return array{texto: string, modelo: string, cortada: bool}
     */
    public function enviar(string $clave, string $mensaje, string $system, int $maxTokens): array
    {
        // H-1 (QA): tope por llamada y sin reintentos del adaptador, para que intento + reintento de validación
        // (2 llamadas) quepan en el timeout de 170 s de los trabajos.
        $r = IA::enviar($clave, $mensaje, [], $system, [], [
            'json' => true, 'max_tokens' => $maxTokens, 'timeout' => self::TIMEOUT_LLAMADA, 'reintentos' => 0,
        ]);

        // `cortada`: el proveedor paró por el tope de tokens (la respuesta quedó incompleta).
        return ['texto' => (string) ($r['texto'] ?? ''), 'modelo' => (string) ($r['modelo'] ?? ''), 'cortada' => ($r['fin'] ?? null) === 'max_tokens'];
    }
}
