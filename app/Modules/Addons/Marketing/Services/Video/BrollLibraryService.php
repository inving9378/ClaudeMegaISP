<?php

namespace App\Modules\Addons\Marketing\Services\Video;

use App\Models\Marketing\Asset;
use App\Models\Marketing\MarketingNiche;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Support\Facades\Log;

class BrollLibraryService
{
    protected const PEXELS_API_URL = 'https://api.pexels.com/videos/search';
    protected const MAX_VIDEO_DURATION = 30;

    public function __construct(protected int $companyId = 1)
    {}

    public function downloadInitialLibrary(int $clipsPerTag = 5): array
    {
        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            Log::channel('marketing')->warning('BrollLibraryService: no Pexels key, skipping download');
            return ['downloaded' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'No Pexels API key'];
        }

        $niches = MarketingNiche::where('company_id', $this->companyId)
            ->where('active', true)
            ->get();

        $stats = ['downloaded' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($niches as $niche) {
            $tags = array_slice($niche->broll_tags ?? [], 0, 3);
            foreach ($tags as $tag) {
                $result = $this->downloadClipsForTag($tag, $niche->slug, $apiKey, $clipsPerTag);
                $stats['downloaded'] += $result['downloaded'];
                $stats['skipped']    += $result['skipped'];
                $stats['failed']     += $result['failed'];
            }
        }

        return $stats;
    }

    /**
     * Descarga clips solo para un nicho específico, usando todas sus tags.
     * Útil para re-descargar un nicho sin afectar los demás.
     */
    public function downloadForNiche(string $nicheSlug, int $clipsPerTag = 5): array
    {
        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            return ['downloaded' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'No Pexels API key'];
        }

        $niche = MarketingNiche::where('company_id', $this->companyId)
            ->where('slug', $nicheSlug)
            ->first();

        if (!$niche) {
            return ['downloaded' => 0, 'skipped' => 0, 'failed' => 0, 'error' => "Niche '{$nicheSlug}' not found"];
        }

        $stats = ['downloaded' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($niche->broll_tags ?? [] as $tag) {
            $result = $this->downloadClipsForTag($tag, $nicheSlug, $apiKey, $clipsPerTag);
            $stats['downloaded'] += $result['downloaded'];
            $stats['skipped']    += $result['skipped'];
            $stats['failed']     += $result['failed'];
        }

        return $stats;
    }

    public function findClipsByTags(array $tags, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Asset::where('company_id', $this->companyId)
            ->where('type', 'broll')
            ->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->limit($limit)
            ->get();
    }

    public function getRandomClipForNiche(string $nicheSlug): ?Asset
    {
        return Asset::where('company_id', $this->companyId)
            ->where('type', 'broll')
            ->where('category', $nicheSlug)
            ->whereNotNull('path')
            ->inRandomOrder()
            ->first();
    }

    public function getClipAbsPath(Asset $asset): string
    {
        return storage_path('app/' . $asset->path);
    }

    protected function downloadClipsForTag(string $tag, string $nicheSlug, string $apiKey, int $limit): array
    {
        $stats = ['downloaded' => 0, 'skipped' => 0, 'failed' => 0];

        $response = $this->fetchPexelsVideos($tag, $apiKey, $limit);
        if (!$response || empty($response['videos'])) {
            $stats['failed']++;
            return $stats;
        }

        foreach (array_slice($response['videos'], 0, $limit) as $video) {
            $pexelsId = (string) $video['id'];

            // Idempotent: skip if already downloaded
            if (Asset::where('source', 'pexels')->where('external_id', $pexelsId)->exists()) {
                $stats['skipped']++;
                continue;
            }

            // Blacklist check: descartar clips que no corresponden al nicho
            if (!$this->isVideoRelevantToNiche($video, $nicheSlug)) {
                $stats['skipped']++;
                continue;
            }

            $file   = $this->selectBestFile($video['video_files'] ?? []);
            if (!$file) {
                $stats['failed']++;
                continue;
            }

            $dir      = storage_path("app/marketing/assets/broll/{$nicheSlug}");
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $filename = "pexels_{$pexelsId}.mp4";
            $destPath = "{$dir}/{$filename}";
            $relPath  = "marketing/assets/broll/{$nicheSlug}/{$filename}";

            if ($this->downloadFile($file['link'], $destPath)) {
                Asset::create([
                    'company_id'       => $this->companyId,
                    'type'             => 'broll',
                    'category'         => $nicheSlug,
                    'name'             => "B-roll: {$tag} (Pexels {$pexelsId})",
                    'path'             => $relPath,
                    'mime_type'        => 'video/mp4',
                    'source'           => 'pexels',
                    'external_id'      => $pexelsId,
                    'duration_seconds' => $video['duration'] ?? 0,
                    'width'            => $file['width'] ?? 0,
                    'height'           => $file['height'] ?? 0,
                    'tags'             => [$tag, $nicheSlug],
                    'license'          => 'pexels_free',
                    'metadata'         => [
                        'pexels_url'  => $video['url'] ?? '',
                        'author'      => $video['user']['name'] ?? '',
                        'search_tag'  => $tag,
                    ],
                ]);
                $stats['downloaded']++;
            } else {
                $stats['failed']++;
            }

            // Rate limit courtesy pause
            usleep(200000);
        }

        return $stats;
    }

    protected function fetchPexelsVideos(string $query, string $apiKey, int $perPage = 5): ?array
    {
        $url = self::PEXELS_API_URL . '?' . http_build_query([
            'query'    => $query,
            'per_page' => $perPage,
            'size'     => 'medium',
        ]);

        $retries = 0;
        while ($retries < 3) {
            $ctx = stream_context_create([
                'http' => [
                    'header'  => "Authorization: {$apiKey}\r\n",
                    'timeout' => 15,
                ],
            ]);

            $body = @file_get_contents($url, false, $ctx);
            if ($body !== false) {
                return json_decode($body, true);
            }

            // Check for 429 rate limit
            $responseHeaders = $http_response_header ?? [];
            $statusLine      = $responseHeaders[0] ?? '';
            if (str_contains($statusLine, '429')) {
                sleep(60);
            }

            $retries++;
        }

        return null;
    }

    protected function selectBestFile(array $files): ?array
    {
        if (empty($files)) {
            return null;
        }

        // Prefer HD portrait (height > width), max quality ≤ 1080p
        usort($files, function ($a, $b) {
            $aPortrait = ($a['height'] ?? 0) > ($a['width'] ?? 0);
            $bPortrait = ($b['height'] ?? 0) > ($b['width'] ?? 0);
            if ($aPortrait !== $bPortrait) {
                return $bPortrait <=> $aPortrait;
            }
            // Prefer higher resolution but not 4K+
            $aPixels = min(($a['width'] ?? 0) * ($a['height'] ?? 0), 1920 * 1080);
            $bPixels = min(($b['width'] ?? 0) * ($b['height'] ?? 0), 1920 * 1080);
            return $bPixels <=> $aPixels;
        });

        foreach ($files as $file) {
            if (!empty($file['link']) && ($file['width'] ?? 0) > 0) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Verifica que el clip no pertenezca a una categoría incorrecta para el nicho.
     * Revisa la URL y título del video contra una blacklist por nicho.
     */
    protected function isVideoRelevantToNiche(array $video, string $nicheSlug): bool
    {
        static $blacklist = [
            'gamer'          => ['piano', 'music instrument', 'classical', 'orchestra', 'sheet music', 'violin', 'guitar playing'],
            'familia'        => ['business meeting', 'corporate', 'office workers', 'conference room'],
            'home_office'    => ['family kids', 'restaurant kitchen', 'retail store'],
            'streamer'       => ['piano', 'classical', 'orchestra', 'violin'],
            'pequeno_negocio'=> ['gaming setup', 'esports', 'kids playing video'],
            'adulto_mayor'   => ['extreme sport', 'nightclub', 'gaming tournament'],
        ];

        $haystack = strtolower(
            ($video['url'] ?? '') . ' ' .
            ($video['user']['name'] ?? '') . ' ' .
            ($video['alt'] ?? '')
        );

        foreach ($blacklist[$nicheSlug] ?? [] as $banned) {
            if (str_contains($haystack, $banned)) {
                Log::channel('marketing')->info('[Pexels] Video descartado por blacklist', [
                    'niche'  => $nicheSlug,
                    'reason' => $banned,
                    'url'    => $video['url'] ?? '',
                ]);
                return false;
            }
        }

        return true;
    }

    protected function downloadFile(string $url, string $destPath): bool
    {
        $ctx  = stream_context_create(['http' => ['timeout' => 120]]);
        $data = @file_get_contents($url, false, $ctx);

        if ($data === false || strlen($data) < 10000) {
            return false;
        }

        return file_put_contents($destPath, $data) !== false;
    }

    protected function resolveApiKey(): ?string
    {
        try {
            return app(ApiIntegrationService::class)->getKey('pexels', $this->companyId);
        } catch (\Throwable) {
            return null;
        }
    }
}
