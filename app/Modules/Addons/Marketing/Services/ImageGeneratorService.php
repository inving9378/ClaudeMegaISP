<?php

namespace App\Modules\Addons\Marketing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGeneratorService
{
    private string $apiToken;
    private string $apiBase = 'https://api.replicate.com/v1';

    // SDXL versión estable
    private string $modelVersion = 'ac732df83cea7fff18b8472768c88ad041fa750ff7682a21affe81863cbe77e4';

    public function __construct()
    {
        $this->apiToken = env('REPLICATEAPITOKEN', '');
    }

    /**
     * Genera una imagen con Stable Diffusion XL vía Replicate.
     * Descarga la imagen resultante y devuelve la URL pública local.
     */
    public function generate(string $prompt): string
    {
        if (empty($this->apiToken)) {
            throw new \RuntimeException('REPLICATEAPITOKEN no está configurado en .env');
        }

        $prediction = $this->createPrediction($prompt);

        if (!$prediction || empty($prediction['id'])) {
            throw new \RuntimeException('No se pudo iniciar la predicción en Replicate.');
        }

        $result = $this->pollUntilDone($prediction['id']);

        if (!$result || $result['status'] !== 'succeeded') {
            $status = $result['status'] ?? 'timeout';
            throw new \RuntimeException("La generación de imagen terminó con estado: {$status}");
        }

        $imageUrl = $result['output'][0] ?? null;
        if (!$imageUrl) {
            throw new \RuntimeException('Replicate no devolvió URL de imagen en el output.');
        }

        return $this->downloadAndStore($imageUrl);
    }

    private function createPrediction(string $prompt): ?array
    {
        $response = Http::withToken($this->apiToken)
            ->timeout(30)
            ->post("{$this->apiBase}/predictions", [
                'version' => $this->modelVersion,
                'input'   => [
                    'prompt'            => $prompt,
                    'width'             => 1024,
                    'height'            => 1024,
                    'num_outputs'       => 1,
                    'guidance_scale'    => 7.5,
                    'num_inference_steps' => 30,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('ImageGeneratorService::createPrediction error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json();
    }

    private function pollUntilDone(string $predictionId, int $maxAttempts = 30): ?array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(3);

            $response = Http::withToken($this->apiToken)
                ->timeout(15)
                ->get("{$this->apiBase}/predictions/{$predictionId}");

            if (!$response->successful()) {
                continue;
            }

            $data = $response->json();

            if (in_array($data['status'] ?? '', ['succeeded', 'failed', 'canceled'])) {
                return $data;
            }
        }

        return null;
    }

    private function downloadAndStore(string $imageUrl): string
    {
        $contents = Http::timeout(60)->get($imageUrl)->body();
        $filename = 'marketing/images/' . Str::uuid() . '.webp';

        Storage::disk('public')->makeDirectory('marketing/images');
        Storage::disk('public')->put($filename, $contents);

        return Storage::disk('public')->url($filename);
    }
}
