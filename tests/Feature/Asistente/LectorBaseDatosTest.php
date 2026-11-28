<?php

namespace Tests\Feature\Asistente;

use App\Services\Asistente\Datos\ConsultaRechazada;
use App\Services\Asistente\Datos\LectorBaseDatos;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesApplication;

/**
 * JARVIS consulta cualquier tabla, pero solo leyendo y sin secretos (LectorBaseDatos). Sin datos
 * propios: revisa las reglas contra el esquema real de la base de pruebas.
 */
class LectorBaseDatosTest extends TestCase
{
    use CreatesApplication;

    private LectorBaseDatos $lector;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('asistente:esquema:tablas');
        Cache::forget('asistente:esquema:bases');
        $this->lector = app(LectorBaseDatos::class);
    }

    public function test_lee_consultas_normales(): void
    {
        $r = $this->lector->consultar('SELECT COUNT(*) AS total FROM users', [], 5, 100, 10);
        $this->assertSame(['total'], $r['columnas']);

        $r = $this->lector->consultar('WITH x AS (SELECT id FROM users) SELECT * FROM x LIMIT 1', [], 5, 100, 10);
        $this->assertSame(['id'], $r['columnas']);
    }

    public function test_tapa_las_columnas_secretas_del_resultado(): void
    {
        $r = $this->lector->consultar('SELECT * FROM users LIMIT 1', [], 5, 100, 10);
        $i = array_search('password', $r['columnas'], true);
        $this->assertNotFalse($i);
        foreach ($r['filas'] as $fila) {
            $this->assertContains($fila[$i], [null, '••••']);
        }
    }

    /** @dataProvider rechazadas */
    public function test_rechaza_lo_que_no_es_lectura_o_toca_secretos(string $sql): void
    {
        $this->expectException(ConsultaRechazada::class);
        $this->lector->consultar($sql, [], 5, 100, 10);
    }

    public static function rechazadas(): array
    {
        return [
            'escritura'            => ['DELETE FROM users WHERE id = -1'],
            'dos instrucciones'    => ['SELECT 1; DROP TABLE users'],
            'comentario ejecutable' => ['SELECT 1 /*! , password */ FROM users'],
            'contraseña por nombre' => ['SELECT login_user, password FROM users'],
            'contraseña con alias' => ['SELECT u.password AS x FROM users u'],
            'llave dentro de JSON' => ["SELECT JSON_EXTRACT(x, '$.api_key') FROM users"],
            'renombrar con UNION'  => ['SELECT 1 UNION SELECT * FROM users'],
            'renombrar con WITH'   => ['WITH x(a, b) AS (SELECT * FROM users) SELECT b FROM x'],
            'otra base'            => ['SELECT table_name FROM information_schema.tables'],
            'variables'            => ['SELECT @a := 1'],
            'archivo del servidor' => ["SELECT LOAD_FILE('/etc/passwd')"],
            'espera'               => ['SELECT SLEEP(3)'],
        ];
    }
}
