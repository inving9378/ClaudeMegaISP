<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaGraphApiService
{
    private string $graphBase = 'https://graph.facebook.com/v18.0';

    public function fetchLeadDetails(string $leadgenId, int $companyId = 1): array
    {
        $token = Setting::get('meta_page_access_token', $companyId) ?? '';

        return $this->withRetry(function () use ($leadgenId, $token) {
            $response = Http::timeout(15)
                ->get("{$this->graphBase}/{$leadgenId}", ['access_token' => $token]);

            Log::channel('single')->info('[MetaGraph] fetchLeadDetails', [
                'leadgen_id' => $leadgenId,
                'status' => $response->status(),
            ]);

            $response->throw();
            return $response->json();
        });
    }

    public function fetchFormDetails(string $formIdMeta, int $companyId = 1): array
    {
        $token = Setting::get('meta_page_access_token', $companyId) ?? '';

        return $this->withRetry(function () use ($formIdMeta, $token) {
            $response = Http::timeout(15)
                ->get("{$this->graphBase}/{$formIdMeta}", [
                    'access_token' => $token,
                    'fields' => 'id,name,status',
                ]);

            $response->throw();
            return $response->json();
        });
    }

    private function withRetry(callable $fn, int $tries = 3): array
    {
        $delays = [1, 3, 9];
        $lastException = null;

        for ($i = 0; $i < $tries; $i++) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($i < $tries - 1) {
                    sleep($delays[$i]);
                }
            }
        }

        Log::error('[MetaGraph] All retries exhausted', ['error' => $lastException?->getMessage()]);
        return [];
    }
}
