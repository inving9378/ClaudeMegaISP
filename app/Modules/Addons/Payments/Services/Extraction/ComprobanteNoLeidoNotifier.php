<?php

namespace App\Modules\Addons\Payments\Services\Extraction;

use App\Models\GeneralNotification;
use App\Models\User;
use App\Notifications\StandardNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Aviso cuando la IA NO pudo leer un comprobante de pago que llegó por WhatsApp.
 *
 * Decisión de Irving (2026-10-02): un comprobante que la IA no pudo leer NO se
 * descarta como "no es comprobante" ni se escala: se avisa por la campana de
 * notificaciones a los roles DESARROLLADOR y super administrador (ES/EN), UN
 * aviso por cada comprobante, con el motivo y qué hacer.
 *
 * Antes, un fallo de la IA (p. ej. Claude sin crédito) dejaba la extracción sin
 * campos y el job la tomaba por "no es comprobante": se perdía en silencio.
 */
class ComprobanteNoLeidoNotifier
{
    /** Roles que reciben el aviso (los dos nombres del rol de super admin existen). */
    public const ROLES = ['DESARROLLADOR', 'super-administrator', 'Super Administrador'];

    /** Pantalla donde se revisan los comprobantes de WhatsApp. */
    private const URL = '/finanzas/whatsapp-comprobantes';

    /**
     * ¿El resultado del extractor es "no se pudo leer"? (distinto de "se leyó y
     * no trae datos de pago", que sí es un no-comprobante).
     */
    public static function noSePudoLeer(array $result): bool
    {
        return ($result['ok'] ?? false) === false && !empty($result['error']);
    }

    /**
     * Motivo legible + qué hacer, a partir del error del extractor.
     *
     * @return array{motivo:string, etiqueta:string, que_hacer:string}
     */
    public static function clasificar(array $result): array
    {
        $error = (string) ($result['error'] ?? '');

        $casos = [
            ['sin_ia', fn() => ($result['motivo'] ?? null) === 'sin_ia',
                'Lectura de comprobantes sin IA utilizable',
                'Asigna una IA a «Leer comprobantes de pago» en Integraciones → Módulos IA y revisa este comprobante a mano.'],
            ['credito_o_llave', fn() => preg_match('/credit balance|insufficient_quota|exceeded your current quota|billing|invalid[ _-]?(x-)?api[ _-]?key|incorrect api key|unauthorized|authentication|\b401\b/i', $error),
                'La IA no tiene crédito o su llave es inválida',
                'Recarga el saldo del proveedor o cambia de IA en Integraciones → Módulos IA. Revisa este comprobante a mano.'],
            ['formato', fn() => preg_match('/no soporta leer PDF|not supported|unsupported|formato/i', $error),
                'El proveedor de IA no puede leer este formato de archivo',
                'Elige en Integraciones → Módulos IA un proveedor que lea imágenes y PDF, o pide al cliente una foto del comprobante.'],
            ['caida', fn() => preg_match('/no respondió a tiempo|timed? ?out|overloaded|rate.?limit|\b429\b|\b5\d\d\b|server error|connection/i', $error),
                'La IA está caída, saturada o tardó demasiado en responder',
                'Revisa este comprobante a mano. Si se repite, revisa el estado del proveedor o cambia de IA en Módulos IA.'],
            ['respuesta_invalida', fn() => stripos($error, 'no se pudo interpretar') !== false,
                'La IA respondió algo que no se pudo interpretar',
                'Revisa este comprobante a mano.'],
            ['archivo', fn() => stripos($error, 'vacío') !== false || stripos($error, 'no soportado') !== false,
                'El archivo del comprobante está vacío o no es válido',
                'Pide al cliente que reenvíe el comprobante.'],
        ];

        foreach ($casos as [$motivo, $coincide, $etiqueta, $queHacer]) {
            if ($coincide()) {
                return ['motivo' => $motivo, 'etiqueta' => $etiqueta, 'que_hacer' => $queHacer];
            }
        }

        return [
            'motivo'    => 'otro',
            'etiqueta'  => 'Error al leer el comprobante con la IA',
            'que_hacer' => 'Revisa este comprobante a mano.',
        ];
    }

    /**
     * Manda el aviso. Best-effort: un fallo aquí se registra pero no rompe el job.
     *
     * @param int    $extractionId id de whatsapp_payment_extractions
     * @param string $contacto     número/nombre de quien mandó el comprobante
     * @param string $origen       línea por la que llegó (para el texto)
     */
    public function notificar(array $result, int $extractionId, string $contacto, string $origen): void
    {
        try {
            $c = self::clasificar($result);
            $detalle = mb_substr((string) ($result['error'] ?? ''), 0, 300);

            $notification = new GeneralNotification();
            $notification->priority    = 'Alta';
            $notification->title       = 'No se pudo leer un comprobante de pago';
            $notification->description = "De: {$contacto} ({$origen}). Motivo: {$c['etiqueta']}. "
                . "Qué hacer: {$c['que_hacer']} Detalle: {$detalle}";
            $notification->code        = "pagos-comprobante-no-leido-{$extractionId}";
            $notification->base_url    = self::URL;
            $notification->model       = 'WhatsappPaymentExtraction';
            $notification->model_id    = $extractionId;
            $notification->save();

            $usuarios = User::whereHas('roles', fn($q) => $q->whereIn('name', self::ROLES))
                ->where('estado', 'activo')
                ->get();

            if ($usuarios->isEmpty()) {
                Log::warning('[Pagos] Comprobante no leído y sin usuarios para avisar', ['extraction_id' => $extractionId]);
                return;
            }

            Notification::send($usuarios, new StandardNotification($notification));

            Log::warning('[Pagos] Comprobante no leído por la IA — aviso enviado', [
                'extraction_id' => $extractionId,
                'motivo'        => $c['motivo'],
                'usuarios'      => $usuarios->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Pagos] No se pudo enviar el aviso de comprobante no leído', [
                'extraction_id' => $extractionId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
