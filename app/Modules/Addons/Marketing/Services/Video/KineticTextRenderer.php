<?php

namespace App\Modules\Addons\Marketing\Services\Video;

class KineticTextRenderer
{
    public function __construct(protected FFmpegService $ffmpeg)
    {}

    /**
     * Renders kinetic text segments over a base video using FFmpeg drawtext chains.
     *
     * @param  string $baseVideoPath  Source video (B-roll or color background)
     * @param  array  $segments       [{text, duration_sec, emphasis, animation_type, position}, ...]
     * @param  array  $style          {font_size, color, font_path, ...}
     * @param  string $outputPath
     * @return array  {success, duration_sec, log}
     */
    public function render(
        string $baseVideoPath,
        array  $segments,
        array  $style,
        string $outputPath
    ): array {
        $mode = $style['render_mode'] ?? 'segments';
        if ($mode === 'word_by_word') {
            return $this->renderWordByWord($baseVideoPath, $segments, $style, $outputPath);
        }

        $filters     = [];
        $currentTime = 0.0;
        $fontPath    = $style['font_path'] ?? $this->ffmpeg->defaultFont();

        foreach ($segments as $seg) {
            $start     = $currentTime;
            $end       = $start + (float) ($seg['duration_sec'] ?? 2.0);
            $emphasis  = $seg['emphasis'] ?? 'medium';
            $animation = $seg['animation_type'] ?? 'fade';
            $fontSize  = $this->getFontSizeForEmphasis($emphasis, $style);

            $filters[] = $this->buildDrawtextFilter([
                'text'      => $seg['text'] ?? '',
                'start'     => $start,
                'end'       => $end,
                'animation' => $animation,
                'font_size' => $fontSize,
                'font_path' => $fontPath,
                'color'     => $style['color'] ?? 'white',
                'position'  => $seg['position'] ?? 'center',
                'shadow'    => true,
                'box'       => true,
                'boxcolor'  => $style['boxcolor'] ?? 'black@0.4',
            ]);

            $currentTime = $end + 0.1;
        }

        if (empty($filters)) {
            return ['success' => false, 'duration_sec' => 0, 'log' => ['No segments']];
        }

        $filterComplex = implode(',', $filters);

        $result = $this->ffmpeg->exec([
            '-i', $baseVideoPath,
            '-vf', $filterComplex,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'copy',
            $outputPath,
        ], 120);

        return array_merge($result, ['duration_sec' => $currentTime]);
    }

    /**
     * Word-by-word mode: cada palabra aparece y desaparece con fade sutil.
     * Textos cortos (≤20 chars): word-by-word TikTok-style.
     * Textos largos (>20 chars): wrap a múltiples líneas centradas, aparecen juntas.
     */
    public function renderWordByWord(
        string $baseVideoPath,
        array  $segments,
        array  $style,
        string $outputPath
    ): array {
        $filters      = [];
        $currentTime  = 0.0;
        $fontPath     = $style['font_path'] ?? $this->ffmpeg->defaultFont();
        $color        = $style['color'] ?? 'white';
        $videoWidth   = (int) ($style['width'] ?? 1080);
        $maxTextWidth = $videoWidth - 80;

        foreach ($segments as $seg) {
            $segStart = $currentTime;
            $segEnd   = $segStart + (float) ($seg['duration_sec'] ?? 2.0);
            $text     = trim((string) ($seg['text'] ?? ''));
            $emphasis = $seg['emphasis'] ?? 'medium';
            $fontSize = $this->getFontSizeForEmphasis($emphasis, $style);
            $fadeIn   = 0.08;
            $fadeOut  = 0.08;

            if (mb_strlen($text) > 20) {
                // Multi-line mode: wrap text y mostrar todas las líneas juntas
                $lines  = $this->wrapTextToLines($text, $fontSize, $maxTextWidth);
                $lineH  = (int) ($fontSize * 1.35);
                $totalH = count($lines) * $lineH;
                $alpha  = "if(lt(t,{$segStart}+{$fadeIn}),(t-{$segStart})/{$fadeIn},"
                        . "if(gt(t,{$segEnd}-{$fadeOut}),({$segEnd}-t)/{$fadeOut},1))";

                foreach ($lines as $li => $line) {
                    $yOffset   = $li * $lineH;
                    $filters[] = "drawtext=text='" . $this->escapeDrawtext($line) . "'"
                        . ":fontfile='{$fontPath}'"
                        . ":fontsize={$fontSize}"
                        . ":fontcolor={$color}"
                        . ":alpha='{$alpha}'"
                        . ":x=(w-text_w)/2"
                        . ":y=(h-{$totalH})/2+{$yOffset}"
                        . ":shadowx=3:shadowy=3:shadowcolor=black@0.85"
                        . ":box=1:boxcolor=black@0.5:boxborderw=14"
                        . ":enable='between(t," . number_format($segStart, 3, '.', '') . "," . number_format($segEnd, 3, '.', '') . ")'";
                }
            } else {
                // Word-by-word mode
                $words       = preg_split('/\s+/', $text);
                $wordCount   = max(1, count($words));
                $timePerWord = max(0.22, min(0.65, ($segEnd - $segStart) / $wordCount));

                foreach ($words as $wi => $word) {
                    $wStart = $segStart + $wi * $timePerWord;
                    $wEnd   = min($wStart + $timePerWord + 0.08, $segEnd);
                    $alpha  = "if(lt(t,{$wStart}+{$fadeIn}),(t-{$wStart})/{$fadeIn},"
                            . "if(gt(t,{$wEnd}-{$fadeOut}),({$wEnd}-t)/{$fadeOut},1))";

                    $filters[] = "drawtext=text='" . $this->escapeDrawtext($word) . "'"
                        . ":fontfile='{$fontPath}'"
                        . ":fontsize={$fontSize}"
                        . ":fontcolor={$color}"
                        . ":alpha='{$alpha}'"
                        . ":x=(w-text_w)/2:y=(h-text_h)/2"
                        . ":shadowx=3:shadowy=3:shadowcolor=black@0.85"
                        . ":box=1:boxcolor=black@0.5:boxborderw=14"
                        . ":enable='between(t," . number_format($wStart, 3, '.', '') . "," . number_format($wEnd, 3, '.', '') . ")'";
                }
            }

            $currentTime = $segEnd + 0.05;
        }

        if (empty($filters)) {
            return ['success' => false, 'duration_sec' => 0, 'log' => ['No segments']];
        }

        $result = $this->ffmpeg->exec([
            '-i', $baseVideoPath,
            '-vf', implode(',', $filters),
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '23',
            '-pix_fmt', 'yuv420p', '-c:a', 'copy',
            $outputPath,
        ], 180);

        return array_merge($result, ['duration_sec' => $currentTime]);
    }

    /**
     * Parte texto largo en líneas que quepan en el ancho del viewport.
     * Estimación: cada carácter ≈ fontSize * 0.55px (bold sans-serif).
     */
    protected function wrapTextToLines(string $text, int $fontSize, int $maxWidth): array
    {
        $maxChars = max(1, (int) floor($maxWidth / ($fontSize * 0.55)));
        $words    = preg_split('/\s+/', trim($text));
        $lines    = [];
        $current  = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') $lines[] = $current;

        return $lines ?: [$text];
    }

    /**
     * Hook explosivo: Ken Burns rápido + fade-in + hook text con sombra gruesa.
     * Diseñado para los primeros 3 segundos de un Reel/TikTok.
     */
    public function renderExplosiveHook(
        string $brollPath,
        string $hookText,
        array  $style,
        string $outputPath,
        float  $duration = 3.0
    ): array {
        $fontPath  = $style['font_path'] ?? $this->ffmpeg->defaultFont();
        $fontSize  = (int) ($style['font_size_hook'] ?? ($style['font_size'] ?? 72) + 16);
        $color     = $style['color'] ?? 'white';
        $w         = (int) ($style['width']  ?? 1080);
        $h         = (int) ($style['height'] ?? 1920);

        // Step 1: Ken Burns agresivo (zoom 1.0→1.25 en duration seg)
        $kbTmp = $this->ffmpeg->tempPath('hook_kb_' . uniqid() . '.mp4');
        $kbResult = $this->ffmpeg->applyKenBurns($brollPath, $kbTmp, $duration, [
            'zoom_start'  => 1.0,
            'zoom_end'    => 1.25,
            'pan_x_start' => 0.5,
            'pan_x_end'   => 0.52,
            'pan_y_start' => 0.52,
            'pan_y_end'   => 0.48,
            'width'       => $w,
            'height'      => $h,
        ]);

        $basePath = ($kbResult['success'] && file_exists($kbTmp)) ? $kbTmp : $brollPath;

        // Step 2: fast fade-in (0.15s) + hook text con wrap automático
        $fadeIn = 0.15;
        $alpha  = "if(lt(t,{$fadeIn}),t/{$fadeIn},1)";

        // Dividir hook en líneas que quepan en el ancho del video
        $lines   = $this->wrapTextToLines($hookText, $fontSize, $w - 60);
        $lineH   = (int) ($fontSize * 1.35);
        $totalH  = count($lines) * $lineH;

        $textFilters = [];
        foreach ($lines as $li => $line) {
            $yOffset       = $li * $lineH;
            $textFilters[] = "drawtext=text='" . $this->escapeDrawtext($line) . "'"
                . ":fontfile='{$fontPath}'"
                . ":fontsize={$fontSize}"
                . ":fontcolor={$color}"
                . ":alpha='{$alpha}'"
                . ":x=(w-text_w)/2"
                . ":y=h*0.38-{$totalH}/2+{$yOffset}"
                . ":shadowx=5:shadowy=5:shadowcolor=black@0.9"
                . ":box=1:boxcolor=black@0.5:boxborderw=18"
                . ":enable='gte(t,0.1)'";
        }

        $vfChain = "fade=t=in:st=0:d={$fadeIn}," . implode(',', $textFilters);

        $result = $this->ffmpeg->exec([
            '-i', $basePath,
            '-vf', $vfChain,
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '23',
            '-pix_fmt', 'yuv420p', '-an',
            '-t', (string) $duration,
            $outputPath,
        ], 120);

        @unlink($kbTmp);
        return $result;
    }

    protected function getFontSizeForEmphasis(string $emphasis, array $style): int
    {
        $base = (int) ($style['font_size'] ?? 56);
        return match ($emphasis) {
            'low'     => $base - 12,
            'medium'  => $base,
            'high'    => $base + 16,
            'extreme' => $base + 32,
            default   => $base,
        };
    }

    protected function buildDrawtextFilter(array $opts): string
    {
        $text     = $this->escapeDrawtext($opts['text']);
        $fontPath = $opts['font_path'];
        $fontSize = (int) $opts['font_size'];
        $color    = $opts['color'];
        $start    = number_format((float) $opts['start'], 3, '.', '');
        $end      = number_format((float) $opts['end'], 3, '.', '');
        $fadeIn   = 0.3;
        $fadeOut  = 0.2;

        [$x, $y] = $this->resolvePosition($opts['position'] ?? 'center');

        // Alpha expression: fade in at start, fade out near end
        $alpha = "if(lt(t,{$start}+{$fadeIn}),(t-{$start})/{$fadeIn},"
               . "if(gt(t,{$end}-{$fadeOut}),({$end}-t)/{$fadeOut},1))";

        $filter  = "drawtext=text='{$text}'";
        $filter .= ":fontfile='{$fontPath}'";
        $filter .= ":fontsize={$fontSize}";
        $filter .= ":fontcolor={$color}";
        $filter .= ":alpha='{$alpha}'";
        $filter .= ":x={$x}:y={$y}";
        $filter .= ":enable='between(t,{$start},{$end})'";

        if (!empty($opts['shadow'])) {
            $filter .= ':shadowx=2:shadowy=2:shadowcolor=black@0.7';
        }
        if (!empty($opts['box'])) {
            $boxColor = $opts['boxcolor'] ?? 'black@0.4';
            $filter  .= ":box=1:boxcolor={$boxColor}:boxborderw=10";
        }

        return $filter;
    }

    protected function resolvePosition(string $position): array
    {
        return match ($position) {
            'top'          => ['(w-text_w)/2', 'h*0.15'],
            'center'       => ['(w-text_w)/2', '(h-text_h)/2'],
            'bottom'       => ['(w-text_w)/2', 'h*0.75'],
            'top_left'     => ['w*0.1', 'h*0.15'],
            'bottom_right' => ['w*0.7', 'h*0.75'],
            default        => ['(w-text_w)/2', '(h-text_h)/2'],
        };
    }

    protected function escapeDrawtext(string $text): string
    {
        // FFmpeg drawtext requires escaping: \ : ' %
        return str_replace(
            ['\\',  ':',   "'",    '%'],
            ['\\\\', '\\:', "\\'", '\\%'],
            $text
        );
    }
}
