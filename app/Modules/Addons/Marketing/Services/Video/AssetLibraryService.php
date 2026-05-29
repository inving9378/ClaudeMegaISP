<?php

namespace App\Modules\Addons\Marketing\Services\Video;

use App\Models\Marketing\Asset;
use App\Models\Marketing\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssetLibraryService
{
    protected int    $companyId;
    protected string $pexelsKey;

    public function __construct(int $companyId = 1)
    {
        $this->companyId = $companyId;
        $this->pexelsKey = Setting::get('pexels_api_key', $companyId) ?? '';
    }

    public function findLocal(
        string  $type,
        ?string $category = null,
        ?array  $tags     = null,
        int     $limit    = 10
    ): Collection {
        $query = Asset::where('company_id', $this->companyId)
                      ->where('type', $type);

        if ($category) {
            $query->where('category', $category);
        }

        if ($tags) {
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        return $query->limit($limit)->get();
    }

    public function searchPexels(string $query, string $type = 'video', int $perPage = 5): array
    {
        if (empty($this->pexelsKey)) {
            return ['error' => 'Pexels API key not configured (setting: pexels_api_key)'];
        }

        $endpoint = $type === 'video'
            ? 'https://api.pexels.com/videos/search'
            : 'https://api.pexels.com/v1/search';

        try {
            $response = Http::withHeaders(['Authorization' => $this->pexelsKey])
                ->timeout(15)
                ->get($endpoint, ['query' => $query, 'per_page' => $perPage]);

            if ($response->failed()) {
                return ['error' => 'Pexels API error: ' . $response->status()];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('Pexels search failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function downloadFromPexels(int $pexelsId, string $url, int $companyId = 1): ?Asset
    {
        try {
            $response = Http::timeout(60)->get($url);
            if ($response->failed()) {
                return null;
            }

            $ext  = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
            $path = "marketing/assets/broll/pexels_{$pexelsId}.{$ext}";
            $full = storage_path("app/{$path}");

            $dir = dirname($full);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            file_put_contents($full, $response->body());

            return Asset::create([
                'company_id' => $companyId,
                'type'       => 'video',
                'category'   => 'broll',
                'name'       => "Pexels #{$pexelsId}",
                'path'       => $path,
                'mime_type'  => "video/{$ext}",
                'size_bytes' => filesize($full),
                'source'     => 'pexels',
                'metadata'   => ['pexels_id' => $pexelsId],
                'license'    => 'pexels_license',
            ]);
        } catch (\Throwable $e) {
            Log::channel('marketing')->error("Pexels download {$pexelsId} failed: " . $e->getMessage());
            return null;
        }
    }

    public function getRandom(string $type, string $category, int $companyId = 1): ?Asset
    {
        return Asset::where('company_id', $companyId)
                    ->where('type', $type)
                    ->where('category', $category)
                    ->inRandomOrder()
                    ->first();
    }

    public function pathFor(Asset $asset): string
    {
        return storage_path('app/' . $asset->path);
    }

    public function getIconPath(string $iconName): string
    {
        $base = storage_path('app/marketing/assets/icons');
        $candidates = [
            "{$base}/{$iconName}.svg",
            "{$base}/{$iconName}.png",
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }
}
