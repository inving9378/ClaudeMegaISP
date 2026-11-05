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
 *
 * 2026-10-29: si Google no da resultados (sin llave, la llave sin Geocoding activado, error o
 * nada encontrado), busca en OpenStreetMap, el mismo buscador de direcciones que usan los mapas
 * del sistema. Lo usa la asistencia cuando el GPS falla y la persona escribe dónde está.
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

        return $this->google($query) ?: $this->openStreetMap($query);
    }

    private function google(string $query): array
    {
        $key = $this->resolveApiKey('google-maps', 'GOOGLE_MAPS_API_KEY');
        if (! $key) {
            Log::warning('GeocodingService: sin llave de Google Maps configurada (Hub ni .env).');
            return [];
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address'  => $query,
                'key'      => $key,
                'language' => 'es',
                'region'   => 'mx',
            ]);
        } catch (\Throwable $e) {
            Log::warning('GeocodingService: Google no respondió.', ['error' => $e->getMessage()]);
            return [];
        }

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

    /** Nominatim de OpenStreetMap: sin llave; pide identificarse y pocas consultas. */
    private function openStreetMap(string $query): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('app.name', 'MegaISP') . ' - asistencia Talento'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q'               => $query,
                    'format'          => 'jsonv2',
                    'countrycodes'    => 'mx',
                    'accept-language' => 'es',
                    'limit'           => 5,
                ]);
        } catch (\Throwable $e) {
            Log::warning('GeocodingService: OpenStreetMap no respondió.', ['error' => $e->getMessage()]);
            return [];
        }

        if (! $response->successful()) {
            Log::warning('GeocodingService: OpenStreetMap respondió con error HTTP.', [
                'status' => $response->status(),
            ]);
            return [];
        }

        $resultados = [];
        foreach ((array) $response->json() as $r) {
            if (! isset($r['lat'], $r['lon'])) {
                continue;
            }
            $resultados[] = [
                'lat'                  => (float) $r['lat'],
                'lng'                  => (float) $r['lon'],
                'direccion_formateada' => $r['display_name'] ?? $query,
            ];
        }

        return $resultados;
    }
}
