<?php

namespace App\Modules\Addons\Marketing\Services\Video;

use App\Models\Marketing\Setting;
use Illuminate\Support\Facades\Log;

class FFmpegService
{
    protected string $ffmpegBin;
    protected string $ffprobeBin;
    protected array  $log = [];
    protected int    $companyId;

    public function __construct(int $companyId = 1)
    {
        $this->companyId  = $companyId;
        $this->ffmpegBin  = Setting::get('ffmpeg_path',  $companyId) ?? '/usr/bin/ffmpeg';
        $this->ffprobeBin = Setting::get('ffprobe_path', $companyId) ?? '/usr/bin/ffprobe';
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    public function exec(array $args, ?int $timeoutSec = 300): array
    {
        $cmd = array_map('escapeshellarg', array_merge([$this->ffmpegBin, '-y'], $args));
        return $this->runCommand(implode(' ', $cmd), $timeoutSec);
    }

    public function probe(string $filePath): array
    {
        $cmd = implode(' ', [
            escapeshellarg($this->ffprobeBin),
            '-v quiet -print_format json -show_format -show_streams',
            escapeshellarg($filePath),
        ]);
        $result = $this->runCommand($cmd, 30);
        if (!$result['success']) {
            return [];
        }
        $data   = json_decode($result['stdout'], true) ?? [];
        $format = $data['format'] ?? [];
        $video  = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video') ?? [];
        return [
            'duration'   => (float)  ($format['duration'] ?? 0),
            'width'      => (int)    ($video['width']  ?? 0),
            'height'     => (int)    ($video['height'] ?? 0),
            'bitrate'    => (int)    ($format['bit_rate'] ?? 0),
            'size_bytes' => (int)    ($format['size']     ?? 0),
            'codec'      => $video['codec_name'] ?? '',
        ];
    }

    public function imageToVideo(
        string $imagePath,
        float  $durationSec,
        string $outputPath,
        int    $width  = 1080,
        int    $height = 1920
    ): bool {
        $result = $this->exec([
            '-loop', '1',
            '-i', $imagePath,
            '-vf', "scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},format=yuv420p",
            '-c:v', 'libx264',
            '-t', (string) $durationSec,
            '-pix_fmt', 'yuv420p',
            '-r', '30',
            $outputPath,
        ], 120);
        return $result['success'];
    }

    public function concatVideos(array $inputPaths, string $outputPath): bool
    {
        // Write a concat list file
        $listFile = $this->tempPath('concat_' . uniqid() . '.txt');
        $lines = array_map(fn($p) => "file '" . addslashes($p) . "'", $inputPaths);
        file_put_contents($listFile, implode("\n", $lines));

        $result = $this->exec([
            '-f', 'concat',
            '-safe', '0',
            '-i', $listFile,
            '-c', 'copy',
            $outputPath,
        ], 300);

        @unlink($listFile);
        return $result['success'];
    }

    public function muxAudio(
        string  $videoPath,
        string  $voicePath,
        ?string $musicPath,
        string  $outputPath,
        float   $voiceVolume = 1.0,
        float   $musicVolume = 0.25
    ): bool {
        if ($musicPath && file_exists($musicPath)) {
            // Voice + music: loop music to match voice duration, use voice as reference length
            $filter = "[1:a]volume={$voiceVolume}[voice];"
                    . "[2:a]aloop=-1:size=2000000000,volume={$musicVolume}[musiclooped];"
                    . "[voice][musiclooped]amix=inputs=2:duration=first[out]";
            $result = $this->exec([
                '-i', $videoPath,
                '-i', $voicePath,
                '-i', $musicPath,
                '-filter_complex', $filter,
                '-map', '0:v',
                '-map', '[out]',
                '-c:v', 'copy',
                '-c:a', 'aac',
                '-shortest',
                $outputPath,
            ], 300);
        } else {
            // Voice only
            $result = $this->exec([
                '-i', $videoPath,
                '-i', $voicePath,
                '-filter_complex', "[1:a]volume={$voiceVolume}[out]",
                '-map', '0:v',
                '-map', '[out]',
                '-c:v', 'copy',
                '-c:a', 'aac',
                '-shortest',
                $outputPath,
            ], 300);
        }
        return $result['success'];
    }

    public function overlayImage(
        string $videoPath,
        string $imagePath,
        string $outputPath,
        string $position  = 'top-right',
        int    $marginPx  = 30
    ): bool {
        $pos = match ($position) {
            'top-left'      => "x={$marginPx}:y={$marginPx}",
            'top-right'     => "x=W-w-{$marginPx}:y={$marginPx}",
            'top-center'    => "x=(W-w)/2:y={$marginPx}",
            'center'        => "x=(W-w)/2:y=(H-h)/2",
            'bottom-left'   => "x={$marginPx}:y=H-h-{$marginPx}",
            'bottom-right'  => "x=W-w-{$marginPx}:y=H-h-{$marginPx}",
            'bottom-center' => "x=(W-w)/2:y=H-h-{$marginPx}",
            default         => "x=W-w-{$marginPx}:y={$marginPx}",
        };

        $result = $this->exec([
            '-i', $videoPath,
            '-i', $imagePath,
            '-filter_complex', "[0:v][1:v]overlay={$pos}:format=auto[out]",
            '-map', '[out]',
            '-map', '0:a?',
            '-c:v', 'libx264',
            '-c:a', 'copy',
            $outputPath,
        ], 300);
        return $result['success'];
    }

    public function drawText(
        string $videoPath,
        string $text,
        array  $opts,
        string $outputPath
    ): bool {
        $fontFile  = $opts['font_file']  ?? $this->defaultFont();
        $fontSize  = $opts['font_size']  ?? 60;
        $fontColor = $opts['color']      ?? 'white';
        $x         = $opts['x']          ?? '(w-text_w)/2';
        $y         = $opts['y']          ?? '(h-text_h)/2';
        $startSec  = $opts['start']      ?? 0;
        $endSec    = $opts['end']        ?? 9999;

        // Escape special characters for drawtext
        $escaped = str_replace(['\\', ':', "'", '%'], ['\\\\', '\\:', "\\'", '\\%'], $text);

        $filter = "drawtext=text='{$escaped}'"
                . ":fontfile=" . escapeshellarg($fontFile)
                . ":fontsize={$fontSize}"
                . ":fontcolor={$fontColor}"
                . ":x={$x}:y={$y}"
                . ":enable='between(t,{$startSec},{$endSec})'";

        if (!empty($opts['box'])) {
            $filter .= ":box=1:boxcolor=" . ($opts['box_color'] ?? 'black@0.5') . ":boxborderw=" . ($opts['box_border'] ?? 10);
        }

        $result = $this->exec([
            '-i', $videoPath,
            '-vf', $filter,
            '-c:v', 'libx264',
            '-c:a', 'copy',
            $outputPath,
        ], 300);
        return $result['success'];
    }

    /**
     * Concatena videos con transición xfade entre cada par.
     * Las escenas de entrada deben ser video-only (sin audio).
     */
    public function concatVideosWithTransition(
        array  $inputs,
        string $outputPath,
        string $transitionType     = 'fade',
        float  $transitionDuration = 0.5
    ): array {
        if (count($inputs) < 2) {
            $ok = $this->concatVideos($inputs, $outputPath);
            return ['success' => $ok];
        }

        $durations = [];
        foreach ($inputs as $input) {
            $probe       = $this->probe($input);
            $durations[] = max((float) ($probe['duration'] ?? 5.0), $transitionDuration + 0.05);
        }

        $filters       = [];
        $currentOffset = 0.0;
        $prevLabel     = '[0:v]';

        for ($i = 1, $n = count($inputs); $i < $n; $i++) {
            $currentOffset += $durations[$i - 1] - $transitionDuration;
            $outLabel       = ($i === $n - 1) ? '[vout]' : "[v{$i}]";

            $filters[] = sprintf(
                '%s[%d:v]xfade=transition=%s:duration=%.2f:offset=%.4f%s',
                $prevLabel, $i, $transitionType, $transitionDuration, $currentOffset, $outLabel
            );

            $prevLabel = $outLabel;
        }

        $cmd = [];
        foreach ($inputs as $input) {
            $cmd[] = '-i';
            $cmd[] = $input;
        }
        array_push($cmd,
            '-filter_complex', implode(';', $filters),
            '-map', '[vout]',
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '18',
            '-pix_fmt', 'yuv420p', '-an',
            $outputPath
        );

        return $this->exec($cmd, 300);
    }

    public function getRandomProTransition(): string
    {
        static $proTransitions = ['fade', 'slideleft', 'slideright', 'wipeleft', 'circleopen', 'distance'];
        return $proTransitions[array_rand($proTransitions)];
    }

    /**
     * Aplica efecto Ken Burns (zoom+pan) a un clip de video.
     * Usa el filtro zoompan de FFmpeg con interpolación temporal.
     */
    public function applyKenBurns(string $inputPath, string $outputPath, float $durationSec, array $opts = []): array
    {
        $opts = array_merge([
            'zoom_start'  => 1.0,
            'zoom_end'    => 1.12,
            'pan_x_start' => 0.5,
            'pan_x_end'   => 0.5,
            'pan_y_start' => 0.5,
            'pan_y_end'   => 0.5,
            'width'       => 1080,
            'height'      => 1920,
        ], $opts);

        $dur       = max($durationSec, 0.1);
        $zStart    = (float) $opts['zoom_start'];
        $zEnd      = (float) $opts['zoom_end'];
        $pxStart   = (float) $opts['pan_x_start'];
        $pxEnd     = (float) $opts['pan_x_end'];
        $pyStart   = (float) $opts['pan_y_start'];
        $pyEnd     = (float) $opts['pan_y_end'];
        $w         = (int) $opts['width'];
        $h         = (int) $opts['height'];

        // z interpolated linearly by timestamp t
        $zExpr  = sprintf('min(%F+(%F)*t/%F,%F)', $zStart, $zEnd - $zStart, $dur, $zEnd);
        // x,y: center crop with pan offset
        $xExpr  = sprintf('(iw/2-(iw/zoom/2))+((%F)+(%F)*t/%F-0.5)*(iw-iw/zoom)', $pxStart, $pxEnd - $pxStart, $dur);
        $yExpr  = sprintf('(ih/2-(ih/zoom/2))+((%F)+(%F)*t/%F-0.5)*(ih-ih/zoom)', $pyStart, $pyEnd - $pyStart, $dur);

        $vf = "zoompan=z='{$zExpr}':x='{$xExpr}':y='{$yExpr}':d=1:s={$w}x{$h}:fps=30,setpts=PTS-STARTPTS";

        return $this->exec([
            '-i', $inputPath,
            '-vf', $vf,
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '18',
            '-pix_fmt', 'yuv420p', '-an',
            '-t', (string) $dur,
            $outputPath,
        ], 180);
    }

    public function getRandomKenBurnsPreset(): array
    {
        static $presets = [
            ['zoom_start' => 1.0,  'zoom_end' => 1.12, 'pan_x_start' => 0.5, 'pan_x_end' => 0.5,  'pan_y_start' => 0.5, 'pan_y_end' => 0.5],
            ['zoom_start' => 1.0,  'zoom_end' => 1.15, 'pan_x_start' => 0.4, 'pan_x_end' => 0.6,  'pan_y_start' => 0.5, 'pan_y_end' => 0.5],
            ['zoom_start' => 1.05, 'zoom_end' => 1.18, 'pan_x_start' => 0.5, 'pan_x_end' => 0.5,  'pan_y_start' => 0.6, 'pan_y_end' => 0.4],
            ['zoom_start' => 1.20, 'zoom_end' => 1.0,  'pan_x_start' => 0.5, 'pan_x_end' => 0.5,  'pan_y_start' => 0.5, 'pan_y_end' => 0.5],
        ];
        return $presets[array_rand($presets)];
    }

    public function encodeForWeb(string $inputPath, string $outputPath, array $opts = []): bool
    {
        $crf    = $opts['crf']    ?? 23;
        $preset = $opts['preset'] ?? 'medium';

        $result = $this->exec([
            '-i', $inputPath,
            '-c:v', 'libx264',
            '-crf', (string) $crf,
            '-preset', $preset,
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            '-pix_fmt', 'yuv420p',
            $outputPath,
        ], 600);
        return $result['success'];
    }

    public function generateThumbnail(string $videoPath, string $outputPath, float $atSec = 1.0): bool
    {
        $result = $this->exec([
            '-ss', (string) $atSec,
            '-i', $videoPath,
            '-vframes', '1',
            '-q:v', '2',
            $outputPath,
        ], 30);
        return $result['success'];
    }

    public function applyFade(
        string $inputPath,
        string $outputPath,
        float  $fadeInSec  = 0.5,
        float  $fadeOutSec = 0.5
    ): bool {
        $info     = $this->probe($inputPath);
        $duration = $info['duration'] ?? 0;
        $fadeOut  = max(0, $duration - $fadeOutSec);

        $filter = "fade=t=in:st=0:d={$fadeInSec},fade=t=out:st={$fadeOut}:d={$fadeOutSec}";

        $result = $this->exec([
            '-i', $inputPath,
            '-vf', $filter,
            '-c:v', 'libx264',
            '-c:a', 'copy',
            $outputPath,
        ], 300);
        return $result['success'];
    }

    public function colorBackground(
        string $hexColor,
        float  $durationSec,
        string $outputPath,
        int    $width,
        int    $height
    ): bool {
        // Remove # prefix
        $color = ltrim($hexColor, '#');

        $result = $this->exec([
            '-f', 'lavfi',
            '-i', "color=c=0x{$color}:s={$width}x{$height}:r=30:d={$durationSec}",
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            $outputPath,
        ], 60);
        return $result['success'];
    }

    public function getLog(): array
    {
        return $this->log;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    public function tempPath(string $filename): string
    {
        $dir = storage_path('app/marketing/temp');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/' . $filename;
    }

    public function defaultFont(): string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        ];
        foreach ($candidates as $f) {
            if (file_exists($f)) {
                return $f;
            }
        }
        // Try fc-match
        $result = shell_exec('fc-match --format="%{file}" "DejaVu Sans:style=Bold" 2>/dev/null');
        return trim($result ?: '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf');
    }

    // ── Internal ────────────────────────────────────────────────────────────────

    protected function runCommand(string $cmd, ?int $timeoutSec = 300): array
    {
        $this->log[] = $cmd;
        Log::channel('marketing')->debug('FFmpeg exec', ['cmd' => $cmd]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'stdout' => '', 'stderr' => 'Failed to open process', 'exit_code' => -1];
        }

        fclose($pipes[0]);

        $startTime = time();
        $stdout    = '';
        $stderr    = '';
        $exitCode  = -1;

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Capture exit code here — proc_close() returns -1 after this
                $exitCode = $status['exitcode'];
                break;
            }
            if ($timeoutSec && (time() - $startTime) > $timeoutSec) {
                proc_terminate($process, 9);
                $stderr .= "\n[TIMEOUT after {$timeoutSec}s]";
                $exitCode = -1;
                break;
            }
            $stdout .= (string) fread($pipes[1], 4096);
            $stderr .= (string) fread($pipes[2], 4096);
            usleep(50000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $success = ($exitCode === 0);

        if (!$success) {
            Log::channel('marketing')->warning('FFmpeg error', [
                'exit_code' => $exitCode,
                'stderr'    => substr($stderr, -500),
            ]);
        }

        return [
            'success'   => $success,
            'stdout'    => $stdout,
            'stderr'    => $stderr,
            'exit_code' => $exitCode,
        ];
    }
}
