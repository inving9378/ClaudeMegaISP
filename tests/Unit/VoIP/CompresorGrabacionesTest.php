<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\CompresorGrabaciones;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/** WAV → MP3 de las grabaciones: con ffmpeg real, en una carpeta temporal y sin BD. */
class CompresorGrabacionesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (! CompresorGrabaciones::hayFfmpeg()) {
            $this->markTestSkipped('sin ffmpeg');
        }
        $this->dir = sys_get_temp_dir() . '/compresor-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', array_filter(glob($this->dir . '/*') ?: [], 'is_file'));
            @rmdir($this->dir);
        }
    }

    /** Un WAV como los de MixMonitor: 8 kHz, 16 bits, mono. */
    private function wav(string $nombre, int $segundos = 20): string
    {
        $f = $this->dir . '/' . $nombre;
        $p = new Process(['ffmpeg', '-nostdin', '-loglevel', 'error', '-y', '-f', 'lavfi',
            '-i', "sine=frequency=440:duration={$segundos}:sample_rate=8000", '-ac', '1', '-c:a', 'pcm_s16le', $f]);
        $p->mustRun();

        return $f;
    }

    public function test_convierte_apunta_la_llamada_y_quita_el_wav(): void
    {
        $wav = $this->wav('1795000000.12.wav');
        chmod($wav, 0640);
        touch($wav, 1790000000);
        $apuntada = null;

        $r = (new CompresorGrabaciones())->comprimir($wav, function ($w, $m) use (&$apuntada) {
            $apuntada = [$w, $m];
        });

        $mp3 = $this->dir . '/1795000000.12.mp3';
        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertSame($mp3, $r['mp3']);
        $this->assertSame(['1795000000.12.wav', '1795000000.12.mp3'], $apuntada);
        $this->assertFileDoesNotExist($wav);
        $this->assertFileExists($mp3);
        $this->assertSame([], glob($this->dir . '/*.part'));

        // ~75% menos.
        $this->assertLessThan($r['antes'] * 0.30, $r['despues']);
        $this->assertSame(filesize($mp3), $r['despues']);

        // La purga de 90 días cuenta desde la llamada, y los permisos son los del WAV.
        clearstatcache();
        $this->assertSame(1790000000, filemtime($mp3));
        $this->assertSame(0640, fileperms($mp3) & 0777);

        // MP3 de 16 kHz (el de 8 kHz no lo abre cualquier reproductor), mono, 32 kbps.
        $p = new Process(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_name,sample_rate,channels,bit_rate', '-of', 'json', $mp3]);
        $p->mustRun();
        $s = json_decode($p->getOutput(), true)['streams'][0];
        $this->assertSame('mp3', $s['codec_name']);
        $this->assertSame('16000', $s['sample_rate']);
        $this->assertSame(1, $s['channels']);
        $this->assertSame('32000', $s['bit_rate']);
    }

    public function test_si_no_se_puede_apuntar_la_llamada_el_wav_se_queda(): void
    {
        $wav    = $this->wav('cob-57-1795000000.wav', 5);
        $tamano = filesize($wav);

        $r = (new CompresorGrabaciones())->comprimir($wav, function () {
            throw new RuntimeException('se cayó la base');
        });

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('se cayó la base', $r['error']);
        $this->assertFileExists($wav);
        $this->assertSame($tamano, filesize($wav));
        $this->assertSame([], glob($this->dir . '/*.mp3*'));
    }

    public function test_un_wav_danado_se_queda_y_no_deja_basura(): void
    {
        $wav = $this->dir . '/1795000001.1.wav';
        file_put_contents($wav, random_bytes(3000));
        $llamado = false;

        $r = (new CompresorGrabaciones())->comprimir($wav, function () use (&$llamado) {
            $llamado = true;
        });

        $this->assertFalse($r['ok']);
        $this->assertFalse($llamado, 'no se apunta la llamada si no hay MP3');
        $this->assertFileExists($wav);
        $this->assertSame([], glob($this->dir . '/*.mp3*'));
    }

    public function test_solo_acepta_wav(): void
    {
        $otro = $this->dir . '/1795000002.1.mp3';
        file_put_contents($otro, 'x');

        $r = (new CompresorGrabaciones())->comprimir($otro, fn () => null);

        $this->assertFalse($r['ok']);
        $this->assertFileExists($otro);
    }

    public function test_limpia_solo_temporales_viejos(): void
    {
        $viejo = $this->dir . '/a.mp3.part';
        $nuevo = $this->dir . '/b.mp3.part';
        $wav   = $this->dir . '/c.wav';
        $mp3   = $this->dir . '/d.mp3';
        foreach ([$viejo, $nuevo, $wav, $mp3] as $f) {
            file_put_contents($f, 'x');
            touch($f, time() - 7200);
        }
        touch($nuevo);

        $n = (new CompresorGrabaciones())->limpiarTemporales($this->dir);

        $this->assertSame(1, $n);
        $this->assertFileDoesNotExist($viejo);
        $this->assertFileExists($nuevo);
        $this->assertFileExists($wav);
        $this->assertFileExists($mp3);
    }
}
