<?php

namespace App\Modules\Addons\Marketing\Services\AgentTools;

use App\Models\Marketing\Setting;

class CheckCoverageTool
{
    public static function schema(): array
    {
        return [
            'name'         => 'check_coverage',
            'description'  => 'Verifica si hay cobertura de red en una dirección o colonia. Úsala cuando el cliente pregunte si tienen servicio en su zona.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'address'      => ['type' => 'string', 'description' => 'Dirección o calle del cliente'],
                    'neighborhood' => ['type' => ['string', 'null'], 'description' => 'Colonia o fraccionamiento'],
                    'city'         => ['type' => ['string', 'null'], 'description' => 'Ciudad o municipio'],
                ],
                'required' => ['address'],
            ],
        ];
    }

    public function execute(string $address, ?string $neighborhood = null, ?string $city = null): array
    {
        $zonesJson = Setting::get('coverage_zones', 1);
        $zones     = json_decode($zonesJson ?? '[]', true);

        if (empty($zones)) {
            return [
                'has_coverage'      => null,
                'message'           => 'Sistema de verificación de cobertura no configurado. Confirmar disponibilidad directamente con el equipo.',
                'alternative_zones' => [],
            ];
        }

        // Simple text matching against zones list
        $searchTerms = array_filter([$address, $neighborhood, $city]);
        $searchStr   = mb_strtolower(implode(' ', $searchTerms));

        $matched = null;
        $alternatives = [];

        foreach ($zones as $zone) {
            $zoneName  = is_array($zone) ? ($zone['name'] ?? '') : $zone;
            $zoneMatch = mb_strtolower($zoneName);

            // Check if any search term appears in zone name or vice versa
            foreach ($searchTerms as $term) {
                $termLower = mb_strtolower($term);
                if (str_contains($zoneMatch, $termLower) || str_contains($termLower, $zoneMatch)) {
                    $matched = $zoneName;
                    break 2;
                }
            }
            $alternatives[] = is_array($zone) ? ($zone['name'] ?? $zone) : $zone;
        }

        if ($matched) {
            return [
                'has_coverage'      => true,
                'zone'              => $matched,
                'confidence'        => 0.85,
                'alternative_zones' => [],
            ];
        }

        return [
            'has_coverage'      => false,
            'zone'              => null,
            'confidence'        => 0.7,
            'alternative_zones' => array_slice($alternatives, 0, 3),
            'message'           => 'No se encontró cobertura confirmada en esa zona. Puede que estemos expandiendo.',
        ];
    }
}
