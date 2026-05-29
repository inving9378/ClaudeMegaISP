<?php

namespace App\Modules\Addons\Marketing\Services\AgentTools;

use App\Models\Marketing\Lead;

class UpdateLeadTool
{
    private const ALLOWED = [
        'full_name', 'email', 'phone', 'address', 'neighborhood',
        'city', 'state', 'postal_code', 'notes', 'tags',
        'plan_interested', // stored in notes if no dedicated column
    ];

    public static function schema(): array
    {
        return [
            'name'         => 'update_lead',
            'description'  => 'Actualiza información del lead actual en la base de datos. Úsala cuando el cliente te proporcione nuevos datos (nombre, dirección, email, etc.).',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'fields' => [
                        'type'        => 'object',
                        'description' => 'Campos a actualizar. Permitidos: full_name, email, phone, address, neighborhood, city, state, postal_code, notes, tags (array de strings), plan_interested.',
                        'additionalProperties' => true,
                    ],
                ],
                'required' => ['fields'],
            ],
        ];
    }

    public function execute(Lead $lead, array $fields): array
    {
        $allowed = array_intersect_key($fields, array_flip(self::ALLOWED));

        if (empty($allowed)) {
            return ['success' => false, 'error' => 'No se proporcionaron campos válidos para actualizar.'];
        }

        // Handle tags: accept array or comma-separated string
        if (isset($allowed['tags']) && is_string($allowed['tags'])) {
            $allowed['tags'] = array_map('trim', explode(',', $allowed['tags']));
        }

        // plan_interested goes into notes if no dedicated column
        if (isset($allowed['plan_interested'])) {
            $existing = $lead->notes ?? '';
            $allowed['notes'] = trim($existing . "\nPlan de interés: " . $allowed['plan_interested']);
            unset($allowed['plan_interested']);
        }

        $lead->fill($allowed)->save();

        $updatedFields = array_keys($allowed);
        return [
            'success'        => true,
            'updated_fields' => $updatedFields,
            'lead_summary'   => "Lead actualizado: {$lead->full_name}, {$lead->city}",
        ];
    }
}
