<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\EscritorEnv;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * TestCase PURO: no bootea Laravel ni toca la base. EscritorEnv solo manipula un
 * archivo, así que probarlo no debe costar un migrate:fresh.
 *
 * Cada prueba trabaja sobre un .env de juguete en un directorio temporal. El .env
 * real del proyecto NUNCA se toca — y eso también se comprueba.
 */
class EscritorEnvTest extends TestCase
{
    private string $dir;
    private string $env;

    private const BASE = "# Cabecera\nAPP_NAME=MegaISP\nAPP_ENV=local\n\n# Telefonía\nAMI_HOST=127.0.0.1\nAMI_SECRET=viejo\n# comentario intermedio\nDB_DATABASE=megaisp\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/envtest-' . uniqid();
        mkdir($this->dir);
        $this->env = $this->dir . '/.env';
        file_put_contents($this->env, self::BASE);
        chmod($this->env, 0640);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . '/.*') ?: [] as $f) {
            if (! is_dir($f)) {
                @unlink($f);
            }
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @test */
    public function preserva_comentarios_y_orden(): void
    {
        (new EscritorEnv($this->env))->escribir(['AMI_SECRET' => 'nuevo']);
        $r = file_get_contents($this->env);

        $this->assertStringContainsString('# Cabecera', $r);
        $this->assertStringContainsString('# comentario intermedio', $r);
        $this->assertLessThan(strpos($r, 'AMI_HOST'), strpos($r, 'APP_NAME'), 'el orden cambió');
        $this->assertStringContainsString('AMI_SECRET=nuevo', $r);
        $this->assertStringNotContainsString('viejo', $r);
    }

    /** @test */
    public function nunca_deja_una_clave_duplicada(): void
    {
        // Una clave repetida hace que .env tome la última: un bug invisible
        // leyendo por encima.
        file_put_contents($this->env, self::BASE . "AMI_SECRET=repetida\n");

        $res = (new EscritorEnv($this->env))->escribir(['AMI_SECRET' => 'unica']);

        $this->assertSame(1, substr_count(file_get_contents($this->env), 'AMI_SECRET='));
        $this->assertNotEmpty($res['avisos'], 'debía avisar del duplicado');
    }

    /** @test */
    public function agrega_al_final_lo_que_no_existia_sin_tocar_lo_demas(): void
    {
        (new EscritorEnv($this->env))->escribir(['ARI_PASSWORD' => 'generada']);
        $r = file_get_contents($this->env);

        $this->assertStringContainsString('ARI_PASSWORD=generada', $r);
        $this->assertStringContainsString('AMI_SECRET=viejo', $r);
        $this->assertStringContainsString('DB_DATABASE=megaisp', $r);
    }

    /** @test */
    public function respalda_con_marca_de_tiempo_antes_de_escribir(): void
    {
        $res = (new EscritorEnv($this->env))->escribir(['AMI_SECRET' => 'x']);

        $this->assertFileExists($res['respaldo']);
        $this->assertSame(self::BASE, file_get_contents($res['respaldo']), 'el respaldo debe tener el ORIGINAL');
        $this->assertMatchesRegularExpression('/\.bak-\d{8}-\d{6}$/', $res['respaldo']);
    }

    /** @test */
    public function entrecomilla_valores_con_espacios_o_almohadilla(): void
    {
        // Sin comillas, dotenv lee solo hasta el '#' y la app arranca con media
        // credencial.
        (new EscritorEnv($this->env))->escribir(['AMI_SECRET' => 'con espacios y #hash']);

        $this->assertStringContainsString('AMI_SECRET="con espacios y #hash"', file_get_contents($this->env));
    }

    /** @test */
    public function preserva_los_permisos_del_archivo(): void
    {
        // El temporal nace en 600 y el .env va en 640: sin copiar permisos, la
        // escritura atómica los degradaría en silencio.
        $antes = fileperms($this->env) & 0777;
        (new EscritorEnv($this->env))->escribir(['AMI_SECRET' => 'y']);

        $this->assertSame($antes, fileperms($this->env) & 0777);
    }

    /** @test */
    public function no_deja_temporales_huerfanos(): void
    {
        (new EscritorEnv($this->env))->escribir(['AMI_SECRET' => 'z']);

        $this->assertEmpty(glob($this->dir . '/.env.tmp-*') ?: []);
    }

    /** @test */
    public function si_la_validacion_falla_restaura_el_respaldo_y_aborta(): void
    {
        // Una clave con nombre inválido no se escribe como clave, así que la
        // validación posterior detecta que no quedó.
        $w = new EscritorEnv($this->env);

        try {
            $w->escribir(['CLAVE INVALIDA' => 'x']);
            $this->fail('debía lanzar RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('restauró', $e->getMessage());
            $this->assertSame(self::BASE, file_get_contents($this->env), 'el .env debía quedar intacto');
        }
    }

    /** @test */
    public function aborta_si_el_archivo_no_existe(): void
    {
        $this->expectException(RuntimeException::class);
        new EscritorEnv($this->dir . '/no-existe');
    }
}
