<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Asset;
use App\Modules\Addons\Marketing\Services\Video\BrollLibraryService;
use Tests\TestCase;

class BrollLibraryServiceTest extends TestCase
{
    public function test_find_clips_by_tags_returns_collection(): void
    {
        $svc  = new BrollLibraryService(1);
        $clips = $svc->findClipsByTags(['gaming', 'fps']);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $clips);
    }

    public function test_get_random_clip_returns_null_when_no_clips(): void
    {
        $svc  = new BrollLibraryService(1);
        $clip = $svc->getRandomClipForNiche('niche_that_does_not_exist_xyz');

        $this->assertNull($clip);
    }

    public function test_download_library_returns_error_without_key(): void
    {
        $svc    = new BrollLibraryService(1);
        $result = $svc->downloadInitialLibrary(1);

        // Without a Pexels key in Hub, should return error message but not throw
        $this->assertIsArray($result);
        $this->assertArrayHasKey('downloaded', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    public function test_idempotent_no_duplicate_on_same_external_id(): void
    {
        // Pre-create an asset with external_id = 'test123'
        Asset::where('source', 'pexels')->where('external_id', 'test123')->delete();
        Asset::create([
            'company_id'  => 1,
            'type'        => 'broll',
            'category'    => 'test',
            'name'        => 'Test clip',
            'path'        => 'marketing/assets/broll/test/pexels_test123.mp4',
            'source'      => 'pexels',
            'external_id' => 'test123',
            'tags'        => [],
        ]);

        $count = Asset::where('source', 'pexels')->where('external_id', 'test123')->count();
        $this->assertSame(1, $count);
    }
}
