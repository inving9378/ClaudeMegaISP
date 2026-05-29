<?php

namespace App\Modules\Addons\Marketing\Services\AgentTools;

use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadActivity;
use Carbon\Carbon;

class ScheduleAppointmentTool
{
    private const VALID_TYPES = ['installation', 'call', 'visit', 'meeting'];

    private const TYPE_LABELS = [
        'installation' => 'Instalación',
        'call'         => 'Llamada',
        'visit'        => 'Visita',
        'meeting'      => 'Reunión',
    ];

    public static function schema(): array
    {
        return [
            'name'         => 'schedule_appointment',
            'description'  => 'Agenda una cita o instalación para el lead. Úsala cuando el cliente confirme una fecha y hora.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'datetime' => [
                        'type'        => 'string',
                        'description' => 'Fecha y hora en formato ISO 8601 o "YYYY-MM-DD HH:MM" (hora local México)',
                    ],
                    'type' => [
                        'type'        => 'string',
                        'enum'        => self::VALID_TYPES,
                        'description' => 'Tipo de cita: installation (instalación), call (llamada), visit (visita), meeting (reunión)',
                    ],
                    'notes' => [
                        'type'        => ['string', 'null'],
                        'description' => 'Notas adicionales sobre la cita (dirección, instrucciones, etc.)',
                    ],
                ],
                'required' => ['datetime', 'type'],
            ],
        ];
    }

    public function execute(Lead $lead, string $datetime, string $type, ?string $notes = null): array
    {
        if (!in_array($type, self::VALID_TYPES)) {
            return ['success' => false, 'error' => "Tipo de cita inválido: {$type}. Use: " . implode(', ', self::VALID_TYPES)];
        }

        try {
            $dt = Carbon::parse($datetime, 'America/Mexico_City');
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => "Formato de fecha/hora inválido: {$datetime}"];
        }

        $activity = LeadActivity::create([
            'lead_id'     => $lead->id,
            'type'        => 'meeting',
            'description' => (self::TYPE_LABELS[$type] ?? $type) . ' agendada para ' . $dt->format('d/m/Y H:i'),
            'metadata'    => [
                'appointment_type'     => $type,
                'appointment_datetime' => $dt->toIso8601String(),
                'notes'                => $notes,
            ],
            'happened_at' => $dt,
        ]);

        $label = self::TYPE_LABELS[$type] ?? $type;
        $confirmText = "Tu {$label} queda confirmada para el {$dt->isoFormat('dddd D [de] MMMM [a las] H:mm')}. ¿Hay algo más en lo que pueda ayudarte?";

        return [
            'success'          => true,
            'appointment_id'   => $activity->id,
            'confirmation_text'=> $confirmText,
        ];
    }
}
