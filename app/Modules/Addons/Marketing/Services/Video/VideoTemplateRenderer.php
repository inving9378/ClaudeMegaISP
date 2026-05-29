<?php

namespace App\Modules\Addons\Marketing\Services\Video;

use App\Models\Marketing\Asset;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Setting;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VideoTemplateRenderer
{
    protected array $log = [];

    public function __construct(
        protected FFmpegService       $ffmpeg,
        protected TtsService          $tts,
        protected AssetLibraryService $assets,
    ) {}

    // ── Public ──────────────────────────────────────────────────────────────────

    public function render(
        VideoTemplate $template,
        array         $variables,
        string        $aspectRatio  = '9:16',
        int           $companyId    = 1
    ): array {
        $this->log  = [];
        $totalCost  = 0.0;
        $schema     = $template->schema ?? [];
        $jobId      = Str::uuid()->toString();

        try {
            // 1. Validate
            $validation = $this->validateVariables($schema, $variables);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error'], 'log' => $this->log];
            }

            // Validate aspect ratio
            $supported = $schema['supported_aspect_ratios'] ?? ['9:16', '1:1', '16:9'];
            if (!in_array($aspectRatio, $supported)) {
                return [
                    'success' => false,
                    'error'   => "Aspect ratio '{$aspectRatio}' not supported. Supported: " . implode(', ', $supported),
                    'log'     => $this->log,
                ];
            }

            // 2. Resolve brand variables (with per-video overrides)
            $brand   = $this->loadBrand($companyId);
            $flatBrand = $this->flattenBrand($brand);

            // Apply color overrides from variables if present
            if (!empty($variables['_override_primary_color'])) {
                $flatBrand['brand.primary_color'] = $variables['_override_primary_color'];
            }
            if (!empty($variables['_override_secondary_color'])) {
                $flatBrand['brand.secondary_color'] = $variables['_override_secondary_color'];
            }
            if (!empty($variables['_override_logo'])) {
                $flatBrand['brand.logo_path'] = $variables['_override_logo'];
            }

            $context = array_merge($variables, $flatBrand);

            // Flatten brief fields into context so templates can use {{hook}}, {{cta}}, etc.
            $brief = $context['_creative_brief'] ?? [];
            if (!empty($brief['niche_slug'])) $context['_niche_slug'] = $brief['niche_slug'];
            if (!empty($brief['hook']))        $context['hook']        = $brief['hook'];
            if (!empty($brief['cta']))         $context['cta']         = $brief['cta'];

            // 3. Dimensions
            [$width, $height] = $this->applyAspectRatio($aspectRatio);

            // 4. Generate voiceover
            $voiceoverPath = null;
            $voiceDuration = 0.0;

            if (!empty($schema['voiceover_script']) && ($schema['audio']['voiceover']['enabled'] ?? true)) {
                $script = $this->resolveVariables($schema['voiceover_script'], $context);
                // Fall back to fallback_script if the primary still has unresolved placeholders
                if (str_contains($script, '{{') && !empty($schema['voiceover_fallback'])) {
                    $script = $this->resolveVariables($schema['voiceover_fallback'], $context);
                }
                $voiceFile    = "marketing/audio/voiceover/{$companyId}/{$jobId}.mp3";
                $voiceAbsPath = storage_path("app/{$voiceFile}");

                $this->logStep("TTS: synthesizing voiceover ({$script})");
                $ttsResult = $this->tts->synthesize($script, $voiceAbsPath);

                if ($ttsResult['success']) {
                    $voiceoverPath = $voiceAbsPath;
                    $voiceDuration = $ttsResult['duration_sec'];
                    $totalCost    += $ttsResult['cost_usd'] ?? 0;
                } else {
                    // Non-fatal: log warning and continue without voiceover
                    $this->logStep('TTS WARNING: ' . ($ttsResult['error'] ?? 'unknown'));
                }
            }

            // 5. Determine total duration — always use template duration, truncate voiceover if needed
            $schemaSeconds = (float) ($schema['duration_seconds'] ?? 15);
            $totalDuration = $schemaSeconds;

            if ($voiceoverPath && $voiceDuration > $schemaSeconds) {
                $truncTmp = $this->ffmpeg->tempPath("voice_trunc_{$jobId}.mp3");
                $fadeStart = max(0.0, $schemaSeconds - 1.0);
                $this->ffmpeg->exec([
                    '-i', $voiceoverPath,
                    '-t', (string) $schemaSeconds,
                    '-af', "afade=t=out:st={$fadeStart}:d=1.0",
                    '-c:a', 'libmp3lame', '-b:a', '128k',
                    $truncTmp,
                ], 30);
                if (file_exists($truncTmp)) {
                    $voiceoverPath = $truncTmp;
                    $voiceDuration = $schemaSeconds;
                    $this->logStep("Voiceover truncado a {$schemaSeconds}s (original era " . round($voiceDuration, 1) . 's)');
                }
            }

            // 6. Render scenes
            $scenePaths = [];
            $scenes     = $schema['scenes'] ?? [];
            $sceneCount = count($scenes);
            $sceneTime  = $sceneCount > 0 ? ($totalDuration / $sceneCount) : $totalDuration;

            foreach ($scenes as $i => $sceneSchema) {
                $duration    = (float) ($sceneSchema['duration'] ?? $sceneTime);
                $sceneTmp    = $this->ffmpeg->tempPath("scene_{$jobId}_{$i}.mp4");
                $this->logStep("Rendering scene {$i}: {$sceneSchema['type']} ({$duration}s)");

                $rendered = $this->renderScene($sceneSchema, $duration, $sceneTmp, $context, $width, $height);
                if (!$rendered) {
                    return ['success' => false, 'error' => "Scene {$i} render failed", 'log' => $this->log];
                }
                $scenePaths[] = $sceneTmp;
            }

            // 7. Concatenate scenes (with crossfade transition)
            $concatTmp  = $this->ffmpeg->tempPath("concat_{$jobId}.mp4");
            $transition = $variables['_transition_type'] ?? 'fade';
            $transDur   = (float) ($variables['_transition_duration'] ?? 0.5);

            if (count($scenePaths) > 1) {
                $this->logStep("Concatenating " . count($scenePaths) . " scenes with '{$transition}' transition");
                $concatResult = $this->ffmpeg->concatVideosWithTransition($scenePaths, $concatTmp, $transition, $transDur);
                if (!$concatResult['success']) {
                    // Fallback to hard cut if xfade fails
                    $this->logStep('Transition concat failed, falling back to hard cut');
                    if (!$this->ffmpeg->concatVideos($scenePaths, $concatTmp)) {
                        return ['success' => false, 'error' => 'Scene concatenation failed', 'log' => $this->log];
                    }
                }
            } else {
                copy($scenePaths[0], $concatTmp);
            }

            // 7.5. Probe concat duration for audio padding
            $concatInfo = $this->ffmpeg->probe($concatTmp);
            $concatDur  = (float) ($concatInfo['duration'] ?? $totalDuration);

            // 8. Mux audio
            $audioTmp = $this->ffmpeg->tempPath("audio_{$jobId}.mp4");
            if ($voiceoverPath) {
                $this->logStep('Muxing audio');
                // Pad voiceover to video duration to avoid -shortest truncation
                if ($voiceDuration > 0 && $voiceDuration < ($concatDur - 0.5)) {
                    $paddedVoice = $this->ffmpeg->tempPath("voice_padded_{$jobId}.mp3");
                    $this->ffmpeg->exec([
                        '-i', $voiceoverPath,
                        '-af', "apad=whole_dur={$concatDur}",
                        '-c:a', 'libmp3lame', '-b:a', '128k',
                        $paddedVoice,
                    ], 30);
                    if (file_exists($paddedVoice)) {
                        $voiceoverPath = $paddedVoice;
                    }
                }
                $musicPath = null;
                $musicCat  = $schema['audio']['background_music']['asset_category'] ?? null;
                if ($musicCat) {
                    $musicAsset = $this->assets->getRandom('audio', $musicCat, $companyId);
                    if ($musicAsset) {
                        $musicPath = $this->assets->pathFor($musicAsset);
                    }
                }
                $voiceVol = (float) ($schema['audio']['voiceover']['volume'] ?? 1.0);
                $musicVol = (float) ($schema['audio']['background_music']['volume'] ?? 0.25);
                $this->ffmpeg->muxAudio($concatTmp, $voiceoverPath, $musicPath, $audioTmp, $voiceVol, $musicVol);
            } else {
                copy($concatTmp, $audioTmp);
            }

            // 9. Fade
            $fadeTmp = $this->ffmpeg->tempPath("fade_{$jobId}.mp4");
            $this->logStep('Applying fade out');
            $this->ffmpeg->applyFade($audioTmp, $fadeTmp, 0.3, 0.5);

            // 10. Final encode
            $year        = date('Y');
            $month       = date('m');
            $outDir      = storage_path("app/marketing/videos/{$companyId}/{$year}/{$month}");
            if (!is_dir($outDir)) {
                mkdir($outDir, 0775, true);
            }
            $outFile     = "{$outDir}/{$jobId}.mp4";
            $crf         = (int)    (Setting::get('video_quality_crf',  $companyId) ?? 23);
            $preset      = (string) (Setting::get('video_preset',       $companyId) ?? 'medium');

            $this->logStep("Final encode → {$outFile}");
            if (!$this->ffmpeg->encodeForWeb($fadeTmp, $outFile, ['crf' => $crf, 'preset' => $preset])) {
                return ['success' => false, 'error' => 'Final encode failed', 'log' => $this->log];
            }

            // 11. Thumbnail
            $thumbPath = preg_replace('/\\.mp4$/', '_thumb.jpg', $outFile);
            $this->ffmpeg->generateThumbnail($outFile, $thumbPath, 1.0);

            // 12. Probe final
            $finalInfo   = $this->ffmpeg->probe($outFile);
            $finalDur    = $finalInfo['duration'] ?? $totalDuration;

            // 13. Cleanup temp
            $this->cleanupTemp(array_merge($scenePaths, [$concatTmp, $audioTmp, $fadeTmp, $paddedVoice ?? null]));
            if ($voiceoverPath && !str_starts_with($voiceoverPath, storage_path('app/marketing/audio'))) {
                @unlink($voiceoverPath);
            }
            // Cleanup override logo temp file
            if (!empty($variables['_override_logo'])) {
                @unlink(storage_path('app/' . $variables['_override_logo']));
            }

            $relOutputPath = "marketing/videos/{$companyId}/{$year}/{$month}/{$jobId}.mp4";
            $relThumbPath  = "marketing/videos/{$companyId}/{$year}/{$month}/{$jobId}_thumb.jpg";

            return [
                'success'        => true,
                'output_path'    => $relOutputPath,
                'thumbnail_path' => $relThumbPath,
                'duration_sec'   => $finalDur,
                'cost_usd'       => $totalCost,
                'log'            => $this->log,
            ];
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('VideoTemplateRenderer exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => $e->getMessage(), 'log' => $this->log];
        }
    }

    // ── Scene rendering ─────────────────────────────────────────────────────────

    protected function renderScene(
        array  $scene,
        float  $duration,
        string $outputPath,
        array  $context,
        int    $width,
        int    $height
    ): bool {
        $type = $scene['type'] ?? 'info_banner';

        return match ($type) {
            'logo_reveal'                 => $this->renderLogoReveal($scene, $duration, $outputPath, $context, $width, $height),
            'feature_highlight',
            'feature_highlight_modern'    => $this->renderFeatureHighlight($scene, $duration, $outputPath, $context, $width, $height),
            'call_to_action',
            'call_to_action_modern'       => $this->renderCallToAction($scene, $duration, $outputPath, $context, $width, $height),
            'text_quote'                  => $this->renderTextQuote($scene, $duration, $outputPath, $context, $width, $height),
            'coverage_map'                => $this->renderCoverageMap($scene, $duration, $outputPath, $context, $width, $height),
            'broll_overlay'               => $this->renderBrollOverlay($scene, $duration, $outputPath, $context, $width, $height),
            'kinetic_text'                => $this->renderKineticText($scene, $duration, $outputPath, $context, $width, $height),
            'split_screen'                => $this->renderSplitScreen($scene, $duration, $outputPath, $context, $width, $height),
            'info_banner'                 => $this->renderInfoBanner($scene, $duration, $outputPath, $context, $width, $height),
            default                       => $this->renderInfoBanner($scene, $duration, $outputPath, $context, $width, $height),
        };
    }

    protected function renderLogoReveal(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $bgColor  = $this->resolveVariables($this->getLayer($scene, 'background', 'color') ?? '{{brand.primary_color}}', $ctx);
        $company  = $this->resolveVariables('{{brand.company_name}}', $ctx);

        $bgTmp  = $this->ffmpeg->tempPath('bg_lr_' . uniqid() . '.mp4');
        $txtTmp = $this->ffmpeg->tempPath('txt_lr_' . uniqid() . '.mp4');

        // Background
        if (!$this->ffmpeg->colorBackground($bgColor, $dur, $bgTmp, $w, $h)) {
            return false;
        }

        // Company name text
        $font    = $this->ffmpeg->defaultFont();
        $fs      = (int) ($w * 0.055); // ~60px at 1080
        $this->ffmpeg->drawText($bgTmp, $company, [
            'font_file'  => $font,
            'font_size'  => $fs,
            'color'      => 'white',
            'x'          => '(w-text_w)/2',
            'y'          => (string) ((int) ($h * 0.65)),
        ], $txtTmp);

        // Logo overlay if available
        $logoPath = $this->resolveLogoPath($ctx);
        if ($logoPath && file_exists($logoPath)) {
            $logoSize = (int) ($w * 0.35);
            $scaled   = $this->ffmpeg->tempPath('logo_scaled_' . uniqid() . '.png');
            $this->scaleImage($logoPath, $scaled, $logoSize, $logoSize);
            $this->ffmpeg->overlayImage($txtTmp, $scaled, $out, 'center', 0);
            @unlink($scaled);
        } else {
            rename($txtTmp, $out);
            @unlink($bgTmp);
            return true;
        }

        @unlink($bgTmp);
        @unlink($txtTmp);
        return file_exists($out);
    }

    protected function renderFeatureHighlight(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $bgColor    = $this->resolveVariables($scene['bg_color'] ?? '{{brand.secondary_color}}', $ctx);
        $mainText   = $this->resolveVariables($scene['main_text'] ?? '', $ctx);
        $subText    = $this->resolveVariables($scene['sub_text'] ?? '', $ctx);
        $textColor  = $this->resolveVariables($scene['text_color'] ?? '{{brand.text_color}}', $ctx);

        $bgTmp   = $this->ffmpeg->tempPath('bg_fh_' . uniqid() . '.mp4');
        $step    = $bgTmp;

        if (!$this->ffmpeg->colorBackground($bgColor, $dur, $bgTmp, $w, $h)) {
            return false;
        }

        if ($mainText) {
            $mainTmp = $this->ffmpeg->tempPath('main_fh_' . uniqid() . '.mp4');
            $fs      = (int) ($w * 0.10);
            $this->ffmpeg->drawText($step, $mainText, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => $fs,
                'color'     => $textColor,
                'x'         => '(w-text_w)/2',
                'y'         => '(h-text_h)/2 - ' . (int)($h * 0.08),
            ], $mainTmp);
            $step = $mainTmp;
        }

        if ($subText) {
            $subTmp = $this->ffmpeg->tempPath('sub_fh_' . uniqid() . '.mp4');
            $fs     = (int) ($w * 0.045);
            $this->ffmpeg->drawText($step, $subText, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => $fs,
                'color'     => $textColor,
                'x'         => '(w-text_w)/2',
                'y'         => '(h-text_h)/2 + ' . (int)($h * 0.08),
            ], $subTmp);
            $step = $subTmp;
        }

        rename($step, $out);
        @unlink($bgTmp);
        return file_exists($out);
    }

    protected function renderCallToAction(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $bgColor   = $this->resolveVariables($scene['bg_color']  ?? '{{brand.primary_color}}', $ctx);
        $ctaText   = $this->resolveVariables($scene['cta_text']  ?? '¡Contáctanos!', $ctx);
        $phone     = $this->resolveVariables($scene['phone']     ?? '{{phone_cta}}', $ctx);
        $textColor = $this->resolveVariables($scene['text_color'] ?? 'white', $ctx);

        $bgTmp  = $this->ffmpeg->tempPath('bg_cta_' . uniqid() . '.mp4');
        $step   = $bgTmp;

        if (!$this->ffmpeg->colorBackground($bgColor, $dur, $bgTmp, $w, $h)) {
            return false;
        }

        // CTA headline
        $ctaTmp = $this->ffmpeg->tempPath('cta1_' . uniqid() . '.mp4');
        $this->ffmpeg->drawText($step, $ctaText, [
            'font_file' => $this->ffmpeg->defaultFont(),
            'font_size' => (int) ($w * 0.065),
            'color'     => $textColor,
            'x'         => '(w-text_w)/2',
            'y'         => '(h-text_h)/2 - ' . (int)($h * 0.08),
        ], $ctaTmp);
        $step = $ctaTmp;

        // Phone
        if (!empty($phone)) {
            $phoneTmp = $this->ffmpeg->tempPath('cta2_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, $phone, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.055),
                'color'     => $textColor,
                'x'         => '(w-text_w)/2',
                'y'         => '(h-text_h)/2 + ' . (int)($h * 0.06),
            ], $phoneTmp);
            $step = $phoneTmp;
        }

        rename($step, $out);
        @unlink($bgTmp);
        return file_exists($out);
    }

    protected function renderTextQuote(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $bgColor  = $this->resolveVariables($scene['bg_color']  ?? '{{brand.primary_color}}', $ctx);
        $quote    = $this->resolveVariables($scene['quote']     ?? '{{customer_quote}}', $ctx);
        $author   = $this->resolveVariables($scene['author']    ?? '{{customer_name}}', $ctx);
        $textColor = $this->resolveVariables($scene['text_color'] ?? 'white', $ctx);

        $bgTmp  = $this->ffmpeg->tempPath('bg_tq_' . uniqid() . '.mp4');
        $step   = $bgTmp;

        if (!$this->ffmpeg->colorBackground($bgColor, $dur, $bgTmp, $w, $h)) {
            return false;
        }

        // Quote text (wrapped manually for long texts)
        $lines   = $this->wrapText($quote, 22);
        $yStart  = (int) ($h * 0.30);
        $lineH   = (int) ($h * 0.07);

        foreach ($lines as $li => $line) {
            $lineTmp = $this->ffmpeg->tempPath('tq_line' . $li . '_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, $line, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.05),
                'color'     => $textColor,
                'x'         => '(w-text_w)/2',
                'y'         => (string) ($yStart + $li * $lineH),
            ], $lineTmp);
            @unlink($step !== $bgTmp ? $step : '');
            $step = $lineTmp;
        }

        // Author
        $authorTmp = $this->ffmpeg->tempPath('tq_author_' . uniqid() . '.mp4');
        $this->ffmpeg->drawText($step, '— ' . $author, [
            'font_file' => $this->ffmpeg->defaultFont(),
            'font_size' => (int) ($w * 0.038),
            'color'     => 'white@0.8',
            'x'         => '(w-text_w)/2',
            'y'         => (string) ($yStart + count($lines) * $lineH + (int)($h * 0.06)),
        ], $authorTmp);

        rename($authorTmp, $out);
        @unlink($bgTmp);
        @unlink($step !== $bgTmp ? $step : '');
        return file_exists($out);
    }

    protected function renderCoverageMap(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $bgColor   = $this->resolveVariables($scene['bg_color'] ?? '{{brand.primary_color}}', $ctx);
        $zoneName  = $this->resolveVariables($scene['zone'] ?? '{{zone_name}}', $ctx);
        $colonias  = $ctx['colonias_list'] ?? [];
        if (is_string($colonias)) {
            $colonias = explode(',', $colonias);
        }

        $bgTmp = $this->ffmpeg->tempPath('bg_cm_' . uniqid() . '.mp4');
        $step  = $bgTmp;

        if (!$this->ffmpeg->colorBackground($bgColor, $dur, $bgTmp, $w, $h)) {
            return false;
        }

        // Zone title
        $titleTmp = $this->ffmpeg->tempPath('cm_title_' . uniqid() . '.mp4');
        $this->ffmpeg->drawText($step, 'Nueva cobertura: ' . $zoneName, [
            'font_file' => $this->ffmpeg->defaultFont(),
            'font_size' => (int) ($w * 0.055),
            'color'     => 'white',
            'x'         => '(w-text_w)/2',
            'y'         => (string) (int) ($h * 0.18),
        ], $titleTmp);
        $step = $titleTmp;

        // Colonias list (up to 5)
        $yStart = (int) ($h * 0.30);
        $lineH  = (int) ($h * 0.065);
        foreach (array_slice((array) $colonias, 0, 5) as $i => $col) {
            $colTmp = $this->ffmpeg->tempPath('cm_col' . $i . '_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, '✓ ' . trim((string) $col), [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.042),
                'color'     => 'white',
                'x'         => (string) (int) ($w * 0.12),
                'y'         => (string) ($yStart + $i * $lineH),
            ], $colTmp);
            @unlink($step !== $bgTmp ? $step : '');
            $step = $colTmp;
        }

        rename($step, $out);
        @unlink($bgTmp);
        return file_exists($out);
    }

    protected function renderInfoBanner(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $bgColor   = $this->resolveVariables($scene['bg_color']   ?? '{{brand.primary_color}}', $ctx);
        $title     = $this->resolveVariables($scene['title']      ?? '', $ctx);
        $subtitle  = $this->resolveVariables($scene['subtitle']   ?? '', $ctx);
        $body      = $this->resolveVariables($scene['body']       ?? '', $ctx);
        $textColor = $this->resolveVariables($scene['text_color'] ?? 'white', $ctx);

        $bgTmp = $this->ffmpeg->tempPath('bg_ib_' . uniqid() . '.mp4');
        $step  = $bgTmp;

        if (!$this->ffmpeg->colorBackground($bgColor, $dur, $bgTmp, $w, $h)) {
            return false;
        }

        $yPos = (int) ($h * 0.25);

        if ($title) {
            $titleTmp = $this->ffmpeg->tempPath('ib_title_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, $title, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.065),
                'color'     => $textColor,
                'x'         => '(w-text_w)/2',
                'y'         => (string) $yPos,
            ], $titleTmp);
            $step  = $titleTmp;
            $yPos += (int) ($h * 0.12);
        }

        if ($subtitle) {
            $subtTmp = $this->ffmpeg->tempPath('ib_subt_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, $subtitle, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.048),
                'color'     => $textColor,
                'x'         => '(w-text_w)/2',
                'y'         => (string) $yPos,
            ], $subtTmp);
            @unlink($step !== $bgTmp ? $step : '');
            $step  = $subtTmp;
            $yPos += (int) ($h * 0.09);
        }

        if ($body) {
            $lines = $this->wrapText($body, 28);
            foreach ($lines as $li => $line) {
                $lineTmp = $this->ffmpeg->tempPath('ib_line' . $li . '_' . uniqid() . '.mp4');
                $this->ffmpeg->drawText($step, $line, [
                    'font_file' => $this->ffmpeg->defaultFont(),
                    'font_size' => (int) ($w * 0.038),
                    'color'     => $textColor,
                    'x'         => '(w-text_w)/2',
                    'y'         => (string) ($yPos + $li * (int)($h * 0.055)),
                ], $lineTmp);
                @unlink($step !== $bgTmp ? $step : '');
                $step = $lineTmp;
            }
        }

        rename($step, $out);
        @unlink($bgTmp);
        return file_exists($out);
    }

    // ── New scene types (Phase 4.5b) ─────────────────────────────────────────────

    protected function renderBrollOverlay(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $nicheSlug = $this->resolveBrollSource($scene, $ctx);
        $broll     = $this->findBrollClip($nicheSlug, $scene['broll_tags'] ?? [], $ctx);

        if (!$broll) {
            // Fallback to color background
            $bgColor = $this->resolveVariables($scene['bg_color'] ?? '{{brand.primary_color}}', $ctx);
            return $this->ffmpeg->colorBackground($bgColor, $dur, $out, $w, $h);
        }

        $brollPath = storage_path('app/' . $broll->path);
        $scaled    = $this->ffmpeg->tempPath('broll_scaled_' . uniqid() . '.mp4');

        // Scale + crop + Ken Burns in one pass
        $kbPreset = $this->ffmpeg->getRandomKenBurnsPreset();
        $dur4f    = max($dur, 0.1);
        $zStart   = $kbPreset['zoom_start'];
        $zEnd     = $kbPreset['zoom_end'];
        $zExpr    = sprintf('min(%F+(%F)*t/%F,%F)', $zStart, $zEnd - $zStart, $dur4f, $zEnd);
        $xExpr    = sprintf('(iw/2-(iw/zoom/2))+(0.5-0.5)*(iw-iw/zoom)');
        $yExpr    = sprintf('(ih/2-(ih/zoom/2))+(0.5-0.5)*(ih-ih/zoom)');
        $kenBurnsVf = "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},setpts=PTS-STARTPTS,"
                    . "zoompan=z='{$zExpr}':x='{$xExpr}':y='{$yExpr}':d=1:s={$w}x{$h}:fps=30,setpts=PTS-STARTPTS";

        // Darken B-roll if scene requests it (useful for CTA overlay readability)
        if (!empty($scene['bg_darken'])) {
            $f = max(0.1, min(0.9, 1.0 - (float) $scene['bg_darken']));
            $kenBurnsVf .= sprintf(',colorchannelmixer=rr=%.2f:gg=%.2f:bb=%.2f:aa=1', $f, $f, $f);
        }

        $result = $this->ffmpeg->exec([
            '-i', $brollPath,
            '-vf', $kenBurnsVf,
            '-t', (string) $dur,
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '18',
            '-an', '-pix_fmt', 'yuv420p',
            $scaled,
        ], 120);

        if (!$result['success']) {
            // Ken Burns failed — fallback to simple scale+crop
            $this->ffmpeg->exec([
                '-i', $brollPath,
                '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},setpts=PTS-STARTPTS",
                '-t', (string) $dur,
                '-c:v', 'libx264', '-an', '-pix_fmt', 'yuv420p',
                $scaled,
            ], 60);
        }

        if (!file_exists($scaled)) {
            $bgColor = $this->resolveVariables($scene['bg_color'] ?? '{{brand.primary_color}}', $ctx);
            return $this->ffmpeg->colorBackground($bgColor, $dur, $out, $w, $h);
        }

        // If this is the hook scene and we have a brief hook text, use explosive hook
        $isHookScene = ($scene['id'] ?? '') === 'hook';
        $hookText    = $ctx['_creative_brief']['hook'] ?? '';
        $kineticSegments = $this->extractKineticSegmentsFromScene($scene, $ctx);

        if ($isHookScene && !empty($hookText)) {
            $kineticRenderer = new KineticTextRenderer($this->ffmpeg);
            $hookStyle = $this->buildKineticStyle($scene, $w);
            $hookStyle['width']  = $w;
            $hookStyle['height'] = $h;
            $hookResult = $kineticRenderer->renderExplosiveHook($scaled, $hookText, $hookStyle, $out, $dur);
            @unlink($scaled);
            return $hookResult['success'];
        }

        // Apply kinetic text layers from brief if present
        if (!empty($kineticSegments)) {
            $kineticRenderer = new KineticTextRenderer($this->ffmpeg);
            $kineticResult   = $kineticRenderer->render($scaled, $kineticSegments, $this->buildKineticStyle($scene, $w), $out);
            @unlink($scaled);
            return $kineticResult['success'];
        }

        rename($scaled, $out);
        return file_exists($out);
    }

    protected function renderKineticText(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        // Get or generate base video
        $nicheSlug = $this->resolveBrollSource($scene, $ctx);
        $broll     = $this->findBrollClip($nicheSlug, [], $ctx);

        $baseTmp = $this->ffmpeg->tempPath('kt_base_' . uniqid() . '.mp4');

        if ($broll) {
            $brollPath = storage_path('app/' . $broll->path);
            $this->ffmpeg->exec([
                '-i', $brollPath,
                '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h}",
                '-t', (string) $dur,
                '-c:v', 'libx264', '-an', '-pix_fmt', 'yuv420p',
                $baseTmp,
            ], 60);
        }

        if (!file_exists($baseTmp)) {
            $bgColor = $this->resolveVariables($scene['bg_color'] ?? '{{brand.primary_color}}', $ctx);
            $this->ffmpeg->colorBackground($bgColor, $dur, $baseTmp, $w, $h);
        }

        $segments = $this->extractKineticSegmentsFromScene($scene, $ctx);
        if (empty($segments)) {
            // Fallback: treat text field as single segment
            $text = $this->resolveVariables($scene['text'] ?? '', $ctx);
            if ($text) {
                $segments = [['text' => $text, 'duration_sec' => $dur, 'emphasis' => $scene['style'] === 'extreme_centered' ? 'extreme' : 'high', 'position' => 'center']];
            }
        }

        if (empty($segments)) {
            rename($baseTmp, $out);
            return file_exists($out);
        }

        $kineticRenderer = new KineticTextRenderer($this->ffmpeg);
        $result          = $kineticRenderer->render($baseTmp, $segments, $this->buildKineticStyle($scene, $w), $out);
        @unlink($baseTmp);

        return $result['success'];
    }

    protected function renderSplitScreen(array $scene, float $dur, string $out, array $ctx, int $w, int $h): bool
    {
        $nicheSlug = $this->resolveBrollSource($scene, $ctx);
        $broll     = $this->findBrollClip($nicheSlug, [], $ctx);
        $split     = $scene['split'] ?? 'top_broll';  // 'top_broll' or 'left_broll'

        $halfH = (int) ($h / 2);
        $halfW = (int) ($w / 2);

        // Top half: B-roll
        $topTmp = $this->ffmpeg->tempPath('split_top_' . uniqid() . '.mp4');
        if ($broll) {
            $brollPath = storage_path('app/' . $broll->path);
            $this->ffmpeg->exec([
                '-i', $brollPath,
                '-vf', "scale={$w}:{$halfH}:force_original_aspect_ratio=increase,crop={$w}:{$halfH}",
                '-t', (string) $dur,
                '-c:v', 'libx264', '-an', '-pix_fmt', 'yuv420p',
                $topTmp,
            ], 60);
        }
        if (!file_exists($topTmp)) {
            $this->ffmpeg->colorBackground('111111', $dur, $topTmp, $w, $halfH);
        }

        // Bottom half: text on brand color
        $botTmp  = $this->ffmpeg->tempPath('split_bot_' . uniqid() . '.mp4');
        $bgColor = $this->resolveVariables($scene['bg_color'] ?? '{{brand.primary_color}}', $ctx);
        $this->ffmpeg->colorBackground($bgColor, $dur, $botTmp, $w, $halfH);

        $text    = $this->resolveVariables($scene['text'] ?? '', $ctx);
        $subtext = $this->resolveVariables($scene['subtext'] ?? '', $ctx);

        $step = $botTmp;
        if ($text) {
            $textTmp = $this->ffmpeg->tempPath('split_txt_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, $text, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.07),
                'color'     => 'white',
                'x'         => '(w-text_w)/2',
                'y'         => (string) (int) ($halfH * 0.3),
            ], $textTmp);
            @unlink($step !== $botTmp ? $step : '');
            $step = $textTmp;
        }
        if ($subtext) {
            $subTmp = $this->ffmpeg->tempPath('split_sub_' . uniqid() . '.mp4');
            $this->ffmpeg->drawText($step, $subtext, [
                'font_file' => $this->ffmpeg->defaultFont(),
                'font_size' => (int) ($w * 0.05),
                'color'     => 'white@0.9',
                'x'         => '(w-text_w)/2',
                'y'         => (string) (int) ($halfH * 0.6),
            ], $subTmp);
            @unlink($step !== $botTmp ? $step : '');
            $step = $subTmp;
        }

        // Stack top + bottom vertically
        $result = $this->ffmpeg->exec([
            '-i', $topTmp,
            '-i', $step,
            '-filter_complex', "[0:v][1:v]vstack=inputs=2[out]",
            '-map', '[out]',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p',
            $out,
        ], 60);

        @unlink($topTmp);
        @unlink($botTmp);
        if ($step !== $botTmp) {
            @unlink($step);
        }

        return $result['success'];
    }

    // ── Kinetic/B-roll helpers ────────────────────────────────────────────────────

    protected function resolveBrollSource(array $scene, array $ctx): string
    {
        $source = $scene['broll_source'] ?? '';
        if (str_starts_with($source, 'niche:')) {
            return substr($source, 6);
        }
        // Fallback chain: context niche_slug → brief niche_slug → schema niche → default
        return $ctx['_niche_slug']
            ?? ($ctx['_creative_brief']['niche_slug'] ?? null)
            ?? 'familia';
    }

    protected function findBrollClip(string $nicheSlug, array $tags, array $ctx): ?Asset
    {
        // Try local library first
        $asset = Asset::where('company_id', $ctx['company_id'] ?? 1)
            ->where('type', 'broll')
            ->where('category', $nicheSlug)
            ->whereNotNull('path')
            ->inRandomOrder()
            ->first();

        return $asset;
    }

    protected function extractKineticSegmentsFromScene(array $scene, array $ctx): array
    {
        $segments = [];

        foreach ($scene['layers'] ?? [] as $layer) {
            if (($layer['type'] ?? '') !== 'kinetic_text') {
                continue;
            }

            // Segments from creative brief
            if (!empty($layer['segments_from_brief'])) {
                $briefKey = $layer['segments_from_brief'];
                $brief    = $ctx['_creative_brief'] ?? [];
                $segs     = data_get($brief, $briefKey, []);
                if (is_array($segs)) {
                    foreach ($segs as $seg) {
                        $segments[] = [
                            'text'         => $seg['text'] ?? '',
                            'duration_sec' => (float) ($seg['duration_sec'] ?? 1.5),
                            'emphasis'     => $seg['emphasis'] ?? 'medium',
                            'position'     => $layer['position'] ?? 'center',
                        ];
                    }
                } elseif (is_string($segs)) {
                    $segments[] = [
                        'text'         => $segs,
                        'duration_sec' => (float) ($layer['duration_sec'] ?? 2.0),
                        'emphasis'     => 'high',
                        'position'     => $layer['position'] ?? 'center',
                    ];
                }
                continue;
            }

            // Static text
            if (!empty($layer['text'])) {
                $text = $this->resolveVariables($layer['text'], $ctx);
                if ($text) {
                    $segments[] = [
                        'text'         => $text,
                        'duration_sec' => (float) ($layer['duration_sec'] ?? 2.0),
                        'emphasis'     => $this->styleToEmphasis($layer['style'] ?? 'medium'),
                        'position'     => $layer['position'] ?? 'center',
                    ];
                }
            }
        }

        return $segments;
    }

    protected function styleToEmphasis(string $style): string
    {
        return match (true) {
            str_contains($style, 'extreme') => 'extreme',
            str_contains($style, 'huge')    => 'high',
            str_contains($style, 'large')   => 'medium',
            default                          => 'medium',
        };
    }

    protected function buildKineticStyle(array $scene, int $w): array
    {
        $style = $scene['kinetic_style'] ?? [];
        return [
            'font_size'   => (int) ($style['font_size'] ?? ($w * 0.065)),
            'color'       => $style['color']       ?? 'white',
            'font_path'   => $style['font_path']   ?? $this->ffmpeg->defaultFont(),
            'render_mode' => $style['render_mode'] ?? 'word_by_word',
            'boxcolor'    => $style['boxcolor']    ?? 'black@0.45',
            'width'       => $w,
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    public function applyAspectRatio(string $aspectRatio): array
    {
        return match ($aspectRatio) {
            '9:16'  => [1080, 1920],
            '1:1'   => [1080, 1080],
            '16:9'  => [1920, 1080],
            default => [1080, 1920],
        };
    }

    protected function resolveVariables(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($m) use ($vars) {
            $key = trim($m[1]);
            return $vars[$key] ?? $m[0];
        }, $template);
    }

    protected function validateVariables(array $schema, array $variables): array
    {
        $varDefs = $schema['variables'] ?? [];
        foreach ($varDefs as $def) {
            if (($def['required'] ?? false) && empty($variables[$def['key']])) {
                return ['valid' => false, 'error' => "Variable requerida faltante: {$def['key']}"];
            }
        }
        return ['valid' => true];
    }

    protected function loadBrand(int $companyId): array
    {
        $keys = ['brand_logo_path', 'brand_primary_color', 'brand_secondary_color',
                 'brand_text_color', 'brand_company_name', 'brand_phone',
                 'brand_whatsapp', 'brand_website'];
        $brand = [];
        foreach ($keys as $key) {
            $brand[str_replace('brand_', '', $key)] = Setting::get($key, $companyId) ?? '';
        }
        return $brand;
    }

    protected function flattenBrand(array $brand): array
    {
        $flat = [];
        foreach ($brand as $k => $v) {
            $flat["brand.{$k}"] = $v;
        }
        // Defaults
        if (empty($flat['brand.primary_color'])) {
            $flat['brand.primary_color'] = '#1976D2';
        }
        if (empty($flat['brand.secondary_color'])) {
            $flat['brand.secondary_color'] = '#FFC107';
        }
        if (empty($flat['brand.text_color'])) {
            $flat['brand.text_color'] = 'white';
        }
        if (empty($flat['brand.company_name'])) {
            $flat['brand.company_name'] = 'Meganet';
        }
        return $flat;
    }

    protected function resolveLogoPath(array $ctx): ?string
    {
        $rel = $ctx['brand.logo_path'] ?? '';
        if (empty($rel)) {
            return null;
        }
        return storage_path('app/' . $rel);
    }

    protected function getLayer(array $scene, string $type, string $field): ?string
    {
        foreach ($scene['layers'] ?? [] as $layer) {
            if (($layer['type'] ?? '') === $type && isset($layer[$field])) {
                return $layer[$field];
            }
        }
        return null;
    }

    protected function scaleImage(string $src, string $dst, int $w, int $h): void
    {
        $cmd = "convert " . escapeshellarg($src) . " -resize {$w}x{$h} -background none -gravity center -extent {$w}x{$h} " . escapeshellarg($dst) . " 2>/dev/null";
        exec($cmd);
        // Fallback: copy unchanged if convert not available
        if (!file_exists($dst)) {
            copy($src, $dst);
        }
    }

    protected function wrapText(string $text, int $maxChars): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $line  = '';
        foreach ($words as $word) {
            if (mb_strlen($line . ' ' . $word) > $maxChars && $line !== '') {
                $lines[] = trim($line);
                $line    = $word;
            } else {
                $line .= ($line ? ' ' : '') . $word;
            }
        }
        if ($line) {
            $lines[] = trim($line);
        }
        return $lines ?: [$text];
    }

    protected function cleanupTemp(array $paths): void
    {
        foreach ($paths as $p) {
            if ($p && file_exists($p)) {
                @unlink($p);
            }
        }
    }

    protected function logStep(string $msg): void
    {
        $this->log[] = $msg;
        Log::channel('marketing')->debug("[MVM] {$msg}");
    }
}
