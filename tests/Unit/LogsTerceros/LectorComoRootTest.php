<?php

namespace Tests\Unit\LogsTerceros;

use App\Modules\Addons\LogsTerceros\Services\ContrasenaRootIncorrecta;
use App\Modules\Addons\LogsTerceros\Services\LectorComoRoot;
use PHPUnit\Framework\TestCase;

/**
 * «Leer como root» de Logs de terceros, con un `su` simulado (como deploy/system/tests): pide la
 * contraseña en la terminal y corre el comando como el usuario actual. Árbol de MegaISP falso en
 * una carpeta temporal con el lector y su-pty.py reales.
 */
class LectorComoRootTest extends TestCase
{
    private string $dir;

    private string $raiz;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/lector-root-' . bin2hex(random_bytes(4));
        $this->raiz = $this->dir . '/megaisp';
        $real = dirname(__DIR__, 3);
        foreach (['app/Modules/Addons/LogsTerceros/bin/leer-como-root.php', 'app/Modules/Addons/LogsTerceros/Services/LectorLogs.php',
            'deploy/system/su-pty.py'] as $f) {
            @mkdir(dirname($this->raiz . '/' . $f), 0700, true);
            copy($real . '/' . $f, $this->raiz . '/' . $f);
        }
        mkdir($this->dir . '/bin', 0700, true);
        mkdir($this->dir . '/privado', 0700, true);
        file_put_contents($this->dir . '/bin/fake-su', <<<'SH'
            #!/usr/bin/env bash
            printf 'Password: '
            stty -echo
            IFS= read -r pw
            stty echo 2>/dev/null
            printf '\n'
            if [[ "$pw" != "${FAKE_SU_PASSWORD:-}" ]]; then sleep 0.1; echo "su: Authentication failure" >&2; exit 1; fi
            exec bash -c "$3"
            SH);
        chmod($this->dir . '/bin/fake-su', 0700);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function lector(string $via = 'su'): LectorComoRoot
    {
        return new LectorComoRoot($this->raiz, ['MEGAISP_SU_BIN' => $this->dir . '/bin/fake-su', 'FAKE_SU_PASSWORD' => 'raiz-123'], $via);
    }

    public function test_con_la_contrasena_de_root_lee_el_archivo(): void
    {
        $ruta = $this->dir . '/privado/.bash_history';
        file_put_contents($ruta, "ls\ncd /var/log\n");

        $l = $this->lector();
        $this->assertSame(['via' => 'su', 'pide' => 'root'], $l->via());
        $r = $l->leer($ruta, 'raiz-123');
        $this->assertSame("ls\ncd /var/log\n", $r['texto']);
        $this->assertTrue($r['inicio']);

        file_put_contents($ruta, "whoami\n", FILE_APPEND);
        $this->assertSame("whoami\n", $l->leer($ruta, 'raiz-123', despues: $r['hasta'])['texto'], 'seguir también como root');
        $this->assertSame([], glob($this->raiz . '/storage/app/logs-terceros/*'), 'no quedan temporales');
    }

    public function test_contrasena_mala_o_vacia(): void
    {
        $ruta = $this->dir . '/privado/x.log';
        file_put_contents($ruta, "hola\n");
        try {
            $this->lector()->leer($ruta, 'otra-clave');
            $this->fail('debió rechazarla');
        } catch (ContrasenaRootIncorrecta $e) {
            $this->assertSame('Contraseña de root incorrecta.', $e->getMessage());
            $this->assertFalse($e->falta, 'cuenta como intento equivocado');
        }
        try {
            $this->lector()->leer($ruta, '');
            $this->fail('sin contraseña no se intenta');
        } catch (ContrasenaRootIncorrecta $e) {
            $this->assertSame('Escribe la contraseña de root.', $e->getMessage());
            $this->assertTrue($e->falta, 'no escribirla no cuenta como intento');
        }
    }

    public function test_como_root_siguen_las_mismas_reglas(): void
    {
        file_put_contents($this->dir . '/privado/.env', 'APP_KEY=secreto');
        try {
            $this->lector()->leer($this->dir . '/privado/.env', 'raiz-123');
            $this->fail('un .env no se lee ni como root');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('contraseñas o llaves', $e->getMessage());
            $this->assertStringNotContainsString('secreto', $e->getMessage());
        }
        $this->expectExceptionMessage('No existe');
        $this->lector()->leer($this->dir . '/privado/nada.log', 'raiz-123');
    }

    public function test_copia_para_descargar(): void
    {
        $ruta = $this->dir . '/privado/foto.png';
        file_put_contents($ruta, $bytes = "\x89PNG\0\0" . random_bytes(2000));

        $copia = $this->lector()->copiar($ruta, 'raiz-123');
        $this->assertSame($bytes, file_get_contents($copia));
        $this->assertSame(0600, fileperms($copia) & 0777);
        unlink($copia);

        $this->expectException(\RuntimeException::class);
        $this->lector()->copiar($this->dir . '/privado/nada.png', 'raiz-123');
    }

    public function test_sin_contrasena_cuando_ya_hay_root(): void
    {
        $ruta = $this->dir . '/privado/y.log';
        file_put_contents($ruta, "directo\n");
        $l = $this->lector('root');
        $this->assertNull($l->via()['pide']);
        $this->assertSame("directo\n", $l->leer($ruta, null)['texto']);
    }
}
