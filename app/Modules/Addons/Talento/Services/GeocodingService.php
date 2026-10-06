<?php

namespace App\Modules\Addons\Talento\Services;

use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Convierte una dirección de texto en coordenadas reales (Google Geocoding
 * API). Primer consumidor real de la llave 'google-maps' del Hub de
 * Integraciones — antes nadie la usaba para geocodificar, solo se validaba
 * que existiera (ver ApiIntegrationService::validateGoogleMaps()).
 */
class GeocodingService
{
    use UsesApiIntegration;

    /**
     * @return array<int, array{lat: float, lng: float, direccion_formateada: string}>
     */
    public function buscar(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $key = $this->resolveApiKey('google-maps', 'GOOGLE_MAPS_API_KEY');
        if (! $key) {
            Log::warning('GeocodingService: sin llave de Google Maps configurada (Hub ni .env).');
            return [];
        }

        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address'  => $query,
            'key'      => $key,
            'language' => 'es',
            'region'   => 'mx',
        ]);

        if (! $response->successful()) {
            Log::warning('GeocodingService: Geocoding API respondió con error HTTP.', [
                'status' => $response->status(),
            ]);
            return [];
        }

        $body = $response->json();
        if (($body['status'] ?? null) !== 'OK') {
            Log::info('GeocodingService: sin resultados.', [
                'status' => $body['status'] ?? null,
                'query'  => $query,
            ]);
            return [];
        }

        $resultados = [];
        foreach (array_slice($body['results'] ?? [], 0, 5) as $r) {
            $loc = $r['geometry']['location'] ?? null;
            if (! $loc) {
                continue;
            }
            $resultados[] = [
                'lat'                   => (float) $loc['lat'],
                'lng'                   => (float) $loc['lng'],
                'direccion_formateada'  => $r['formatted_address'] ?? $query,
            ];
        }

        return $resultados;
    }
}
