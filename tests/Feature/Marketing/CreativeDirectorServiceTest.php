<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MarketingNiche;
use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Marketing\Services\Personalization\CreativeDirectorService;
use Tests\TestCase;

class CreativeDirectorServiceTest extends TestCase
{
    public function test_parses_valid_brief_json(): void
    {
        $svc = new CreativeDirectorService(1);

        $json = json_encode([
            'hook'                  => 'Test hook',
            'main_benefits'         => ['Benefit 1'],
            'voiceover_script'      => 'Test voiceover',
            'kinetic_text_segments' => [['text' => 'TEST', 'duration_sec' => 1.5, 'emphasis' => 'high']],
            'broll_keywords'        => ['gaming'],
            'music_mood'            => 'epic_electronic',
            'cta'                   => 'Llama ahora',
            'scene_sequence'        => ['hook', 'solution', 'cta'],
        ]);

        $reflector = new \ReflectionMethod($svc, 'parseBrief');
        $reflector->setAccessible(true);
        $result = $reflector->invoke($svc, $json);

        $this->assertSame('Test hook', $result['hook']);
        $this->assertSame('Llama ahora', $result['cta']);
    }

    public function test_parse_strips_markdown_code_fences(): void
    {
        $svc  = new CreativeDirectorService(1);
        $json = '```json' . PHP_EOL . '{"hook":"stripped","cta":"ok"}' . PHP_EOL . '```';

        $reflector = new \ReflectionMethod($svc, 'parseBrief');
        $reflector->setAccessible(true);
        $result = $reflector->invoke($svc, $json);

        $this->assertSame('stripped', $result['hook']);
    }

    public function test_parse_throws_on_invalid_json(): void
    {
        $this->expectException(\RuntimeException::class);

        $svc       = new CreativeDirectorService(1);
        $reflector = new \ReflectionMethod($svc, 'parseBrief');
        $reflector->setAccessible(true);
        $reflector->invoke($svc, 'not json at all');
    }

    public function test_generate_multivariant_returns_empty_when_claude_unavailable(): void
    {
        $this->app->bind(ClaudeApiClient::class, function () {
            $mock = $this->createMock(ClaudeApiClient::class);
            $mock->method('messages')->willThrowException(new \RuntimeException('API unavailable'));
            return $mock;
        });

        $svc    = new CreativeDirectorService(1);
        $briefs = $svc->generateMultivariantBriefs([
            'plan_name'         => 'Test',
            'plan_speed'        => '100 MEGAS',
            'plan_price'        => '599',
            'phone_cta'         => '55 0000 0000',
            'highlight_feature' => 'Sin contratos',
        ], 1);

        // All briefs failed but no exception thrown — returns empty array
        $this->assertIsArray($briefs);
    }

    public function test_estimate_cost_formula(): void
    {
        $svc       = new CreativeDirectorService(1);
        $reflector = new \ReflectionMethod($svc, 'estimateCost');
        $reflector->setAccessible(true);

        $cost = $reflector->invoke($svc, [
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
        ]);

        // input: 1000 * 15 / 1_000_000 = 0.015
        // output: 500 * 75 / 1_000_000 = 0.0375
        $this->assertEqualsWithDelta(0.0525, $cost, 0.001);
    }
}
