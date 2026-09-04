<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\CronScriptsGuard;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * #233 — candado de coherencia crontab↔disco: ningún script de `deploy/circuito/` referenciado
 * por una línea real del crontab puede quedar sin permiso de ejecución (o inexistente) sin que
 * algo lo delate. Nace del incidente de `vigilia-wrap.sh` (modo 100644 el 25-ago, arreglado en
 * 666f17fd) — el caso puntual ya está resuelto, esto es el candado de la CLASE.
 *
 * Usa un crontab de mentira (texto plano) para no depender del `crontab -l` real de la máquina
 * que corre la suite — mismo motivo que `PoolGuardCoherenceTest` no toca la BD real.
 */
class CronScriptsGuardTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/cron-scripts-guard-test-' . uniqid();
        mkdir($this->tmpDir . '/deploy/circuito', 0755, true);
    }

    protected function tearDown(): void
    {
        $wrapper = $this->tmpDir . '/deploy/circuito/wrapper-de-prueba.sh';
        if (file_exists($wrapper)) {
            unlink($wrapper);
        }
        @rmdir($this->tmpDir . '/deploy/circuito');
        @rmdir($this->tmpDir . '/deploy');
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_falla_y_nombra_el_archivo_cuando_el_wrapper_pierde_el_permiso_de_ejecucion(): void
    {
        $wrapper = $this->tmpDir . '/deploy/circuito/wrapper-de-prueba.sh';
        file_put_contents($wrapper, "#!/usr/bin/env bash\necho ok\n");
        chmod($wrapper, 0644); // el defecto real: 100644 en vez de 100755

        $crontab = "* * * * * {$wrapper} circuito:scheduler >/dev/null 2>&1\n";

        $problemas = CronScriptsGuard::problemas($crontab);

        $this->assertNotEmpty($problemas, 'El candado debe fallar cuando el wrapper no es ejecutable.');
        $this->assertSame($wrapper, $problemas[0]['archivo']);
        $this->assertStringContainsString('ejecución', $problemas[0]['motivo']);
    }

    public function test_pasa_cuando_el_wrapper_es_ejecutable(): void
    {
        $wrapper = $this->tmpDir . '/deploy/circuito/wrapper-de-prueba.sh';
        file_put_contents($wrapper, "#!/usr/bin/env bash\necho ok\n");
        chmod($wrapper, 0755);

        $crontab = "* * * * * {$wrapper} circuito:scheduler >/dev/null 2>&1\n";

        $this->assertSame([], CronScriptsGuard::problemas($crontab));
    }

    public function test_falla_cuando_el_archivo_referenciado_no_existe(): void
    {
        $wrapper = $this->tmpDir . '/deploy/circuito/no-existe.sh';
        $crontab = "* * * * * {$wrapper} >/dev/null 2>&1\n";

        $problemas = CronScriptsGuard::problemas($crontab);

        $this->assertNotEmpty($problemas);
        $this->assertSame($wrapper, $problemas[0]['archivo']);
        $this->assertStringContainsString('no existe', $problemas[0]['motivo']);
    }

    public function test_ignora_lineas_comentadas_y_fuera_de_alcance(): void
    {
        $wrapper = $this->tmpDir . '/deploy/circuito/wrapper-de-prueba.sh';
        file_put_contents($wrapper, "#!/usr/bin/env bash\necho ok\n");
        chmod($wrapper, 0644);

        $crontab = implode("\n", [
            "# {$wrapper} >/dev/null 2>&1",
            '0 2 * * * cd /var/www/megaisp && php artisan backup_db:process >> storage/logs/backup-db-cron.log 2>&1',
            '',
        ]);

        $this->assertSame([], CronScriptsGuard::problemas($crontab), 'No debe tocar líneas fuera de deploy/circuito/ ni comentarios.');
    }
}
