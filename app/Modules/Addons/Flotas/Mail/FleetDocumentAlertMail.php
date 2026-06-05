<?php

namespace App\Modules\Addons\Flotas\Mail;

use App\Modules\Addons\Flotas\Models\FleetDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FleetDocumentAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FleetDocument $document,
        public readonly int $daysUntil,
    ) {}

    public function build(): static
    {
        $vehicle  = $this->document->vehicle;
        $typeLabel = FleetDocumentAlertMail::typeLabel($this->document->document_type);
        $vehicleName = $vehicle ? $vehicle->display_name : 'Vehículo desconocido';
        $subject = "⚠️ Documento por vencer: {$typeLabel} de {$vehicleName} ({$this->daysUntil} día(s))";

        return $this->subject($subject)
            ->view('addon-flotas::emails.document_alert', [
                'document'    => $this->document,
                'daysUntil'   => $this->daysUntil,
                'typeLabel'   => $typeLabel,
                'vehicleName' => $vehicleName,
                'vehicle'     => $vehicle,
                'vehicleUrl'  => url('/flotas/' . $this->document->vehicle_id . '?tab=documentos'),
            ]);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'circulation_card'  => 'Tarjeta de circulación',
            'insurance_policy'  => 'Póliza de seguro',
            'tenencia'          => 'Tenencia',
            'verification'      => 'Verificación',
            'operator_license'  => 'Licencia de operador',
            'special_permit'    => 'Permiso especial',
            default             => 'Otro',
        };
    }
}
