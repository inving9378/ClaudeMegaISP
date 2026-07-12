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

        $searchTerms     = array_filter([$neighborhood, $address, $city]);
        $normalizedTerms = array_filter(array_map([self::class, 'normalize'], $searchTerms));

        $matched      = null;
        $alternatives = [];

        foreach ($zones as $zone) {
            $zoneName = is_array($zone) ? ($zone['name'] ?? '') : $zone;
            if ($zoneName === '') {
                continue;
            }

            $normalizedZone = self::normalize($zoneName);

            // Match exacto de colonia (no substring): evita falsos positivos/negativos
            // por coincidencia parcial de nombres de zona.
            foreach ($normalizedTerms as $term) {
                if ($normalizedZone !== '' && $term === $normalizedZone) {
                    $matched = $zoneName;
                    break 2;
                }
            }
            $alternatives[] = $zoneName;
        }

        if ($matched) {
            return [
                'has_coverage'      => true,
                'zone'              => $matched,
                'confidence'        => 0.95,
                'alternative_zones' => [],
            ];
        }

        // Ante duda (sin match exacto) NO se afirma cobertura ni se niega con confianza:
        // se degrada a "confirmar con el equipo" para evitar prometer o descartar servicio
        // por una coincidencia laxa de texto.
        return [
            'has_coverage'      => null,
            'zone'              => null,
            'confidence'        => null,
            'alternative_zones' => array_slice($alternatives, 0, 3),
            'message'           => 'No pude confirmar cobertura exacta en esa colonia. Favor de confirmar disponibilidad con el equipo antes de comprometerse con el cliente.',
        ];
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
