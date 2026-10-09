<?php

namespace App\Modules\Addons\MonitoreoOficial\Notifications;

/**
 * Payload de la notificación de campana (StandardNotification) para un cambio
 * detectado en un expediente. Clase NOMBRADA a propósito — StandardNotification
 * implementa ShouldQueue, así que Laravel serializa este objeto para meterlo en
 * la tabla `jobs`; una clase anónima NO se puede serializar
 * ("Serialization of 'class@anonymous' is not allowed") y la notificación se
 * pierde en silencio (atrapada por el try/catch de CaseChangeNotificationService,
 * pero sin que la campana nunca llegue a aparecer).
 */
class CaseChangeNoticeData
{
    public function __construct(
        public string $topic,
        public string $resumen,
        public int $caseId,
    ) {
    }

    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'reporter' => 'Monitoreo Oficial',
            'resumen' => $this->resumen,
            'case_id' => $this->caseId,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
