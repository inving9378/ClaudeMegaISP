<?php

namespace Tests\Unit\LogsTerceros;

use App\Modules\Addons\LogsTerceros\Services\LectorLogs;
use PHPUnit\Framework\TestCase;

/**
 * «Logs de terceros»: qué rutas se aceptan y cómo se lee (solo lectura). Archivos en una
 * carpeta temporal.
 */
class LectorLogsTest extends TestCase
{
    private string $dir;

    private LectorLogs $lector;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/lector-logs-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/logs', 0700, true);
        $this->lector = new LectorLogs();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function archivo(string $nombre, string $contenido): string
    {
        $ruta = $this->dir . '/logs/' . $nombre;
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function test_acepta_cualquier_archivo_y_rechaza_secretos(): void
    {
        foreach (['/var/log/asterisk/messages.log', '/var/log/nginx/error.log', '/var/log/syslog',
            '/srv/megaisp/storage/logs/laravel.log', '/opt/evolution/logs/app.txt', '/var/lib/megaisp/bot.out',
            '/var/log/asterisk/messages.log-20261114.gz',
            // Desde el 17-nov, no solo logs: configuraciones, código, certificados públicos, respaldos de configuración.
            '/etc/nginx/nginx.conf', '/etc/asterisk/pjsip.conf', '/srv/megaisp/config/database.php',
            '/srv/megaisp/storage/app/reporte.txt', '/etc/hosts', '/etc/ssl/certs/sitio.crt', '/etc/nginx/sites-available/default.bak'] as $bien) {
            $this->assertNull($this->lector->rechazo($bien), $bien);
        }
        foreach (['var/log/syslog' => 'absoluta', '/srv/megaisp/.env' => 'contraseñas', '/var/log/app/produccion.env' => 'contraseñas', '/var/log/app.env.bak' => 'contraseñas', '/var/log/x/secrets.log' => 'contraseñas', '/var/log/../../srv/megaisp/.env' => 'contraseñas',
            '/etc/shadow' => 'contraseñas', '/home/x/.ssh/id_rsa' => 'contraseñas', '/etc/ssl/private/log.key' => '/etc/ssl/private',
            '/proc/self/environ' => '/proc', '/var/log/../../proc/1/environ' => '/proc', '/var/backups/megaisp/x.sql.gz' => 'contraseñas',
            '/root/.my.cnf' => 'contraseñas', '/etc/mysql/debian.cnf' => 'contraseñas', '/srv/megaisp/auth.json' => 'contraseñas',
            '/var/lib/asterisk/astdb.sqlite3' => 'contraseñas', '/home/x/.git-credentials' => 'contraseñas',
            '/run/php/php8.2-fpm.sock' => '/run'] as $mal => $motivo) {
            $this->assertStringContainsString($motivo, (string) $this->lector->rechazo($mal), $mal);
        }
    }

    public function test_lee_lo_ultimo_en_lineas_completas_y_pagina_hacia_atras(): void
    {
        $lineas = '';
        for ($i = 1; $i <= 300; $i++) {
            $lineas .= sprintf("linea %03d %s\n", $i, str_repeat('x', 20));
        }
        $ruta = $this->archivo('app.log', $lineas);

        $a = $this->lector->leer($ruta, bytes: 2048);
        $this->assertStringEndsWith("linea 300 xxxxxxxxxxxxxxxxxxxx\n", $a['texto']);
        $this->assertMatchesRegularExpression('/^linea \d{3} /', $a['texto'], 'empieza en una línea completa');
        $this->assertFalse($a['inicio']);
        $this->assertSame(strlen($lineas), $a['tamano']);

        $b = $this->lector->leer($ruta, hasta: $a['desde'], bytes: 2048);
        $this->assertSame($a['desde'], $b['hasta']);
        $this->assertSame($lineas, $this->hastaElInicio($ruta, 2048), 'pegando las páginas sale el archivo idéntico');

        $todo = $this->lector->leer($ruta, completo: true);
        $this->assertSame($lineas, $todo['texto']);
        $this->assertTrue($todo['inicio']);
    }

    private function hastaElInicio(string $ruta, int $bytes): string
    {
        $p = $this->lector->leer($ruta, bytes: $bytes);
        $texto = $p['texto'];
        while (! $p['inicio']) {
            $p = $this->lector->leer($ruta, hasta: $p['desde'], bytes: $bytes);
            $texto = $p['texto'] . $texto;
        }

        return $texto;
    }

    public function test_seguir_trae_solo_lo_nuevo_y_aguanta_la_rotacion(): void
    {
        $ruta = $this->archivo('app.log', "uno\ndos\n");
        $a = $this->lector->leer($ruta);
        file_put_contents($ruta, "tres\ncuatro a medi", FILE_APPEND);

        $b = $this->lector->leer($ruta, despues: $a['hasta']);
        $this->assertSame("tres\n", $b['texto'], 'la línea a medias llega en la siguiente vuelta');

        file_put_contents($ruta, "nuevo\n");   // se rotó: el archivo es más corto
        $c = $this->lector->leer($ruta, despues: $b['hasta']);
        $this->assertSame("nuevo\n", $c['texto']);
    }

    public function test_quita_colores_de_terminal_y_bytes_invalidos(): void
    {
        $ruta = $this->archivo('messages.log', "\e[0;37m[Nov 14]\e[0m NOTICE\e[1;33m algo\e[0m\nmal \xff\xfe utf8\r\n");
        $t = $this->lector->leer($ruta)['texto'];
        $this->assertSame("[Nov 14] NOTICE algo\nmal ?? utf8\n", str_replace("\u{FFFD}", '?', $t));
    }

    public function test_lee_gz_rotados(): void
    {
        $lineas = '';
        for ($i = 1; $i <= 500; $i++) {
            $lineas .= "registro {$i}\n";
        }
        $ruta = $this->dir . '/logs/messages.log-20261114.gz';
        file_put_contents($ruta, gzencode($lineas));

        $a = $this->lector->leer($ruta, bytes: 1024);
        $this->assertStringEndsWith("registro 500\n", $a['texto']);
        $this->assertSame(strlen($lineas), $a['tamano'], 'tamaño descomprimido');
        $this->assertSame($lineas, $this->hastaElInicio($ruta, 1024));
        $this->assertSame($lineas, $this->lector->leer($ruta, completo: true)['texto']);
    }

    public function test_dice_por_que_no_se_puede_leer(): void
    {
        $this->assertStringContainsString('No existe', (string) $this->lector->info($this->dir . '/logs/nada.log')['motivo']);

        $secreto = $this->dir . '/logs/.env';
        file_put_contents($secreto, 'APP_KEY=x');
        symlink($secreto, $this->dir . '/logs/atajo.log');
        $info = $this->lector->info($this->dir . '/logs/atajo.log');
        $this->assertFalse($info['legible'], 'un enlace a un secreto no sirve de atajo');
        $this->assertStringContainsString('contraseñas', (string) $info['motivo']);

        $this->expectException(\RuntimeException::class);
        $this->lector->leer($this->dir . '/logs/atajo.log');
    }

    public function test_dice_si_no_puede_entrar_a_la_carpeta(): void
    {
        // Como /home/<usuario> con 700 visto por www-data: realpath falla aunque el archivo exista.
        mkdir($this->dir . '/cerrada');
        file_put_contents($this->dir . '/cerrada/historial', "uno\n");
        chmod($this->dir . '/cerrada', 0);
        try {
            if (@is_executable($this->dir . '/cerrada')) {
                $this->markTestSkipped('Corriendo como root: no hay carpetas cerradas.');
            }
            $info = $this->lector->info($this->dir . '/cerrada/historial');
            $this->assertStringContainsString('no tiene permiso para entrar a la carpeta ' . $this->dir . '/cerrada', (string) $info['motivo']);
            $this->assertTrue($info['permiso'], 'la página ofrece leerlo como root');
            $this->assertStringContainsString('entrar a la carpeta', (string) $this->lector->info($this->dir . '/cerrada/*.log')['motivo']);
        } finally {
            chmod($this->dir . '/cerrada', 0700);
        }
        $this->assertSame('Es una carpeta, no un archivo.', $this->lector->info($this->dir . '/cerrada')['motivo']);
        $this->assertSame('No existe.', $this->lector->info($this->dir . '/logs/nada.log')['motivo']);
        $this->assertFalse($this->lector->info($this->dir . '/logs/nada.log')['permiso'], 'root no arregla lo que no existe');
        $this->assertFalse($this->lector->info('/srv/x/.env')['permiso'], 'ni lo prohibido');
    }

    public function test_con_asterisco_muestra_el_mas_reciente_y_nunca_un_secreto(): void
    {
        $viejo = $this->archivo('laravel-2026-11-15.log', "ayer\n");
        $nuevo = $this->archivo('laravel-2026-11-16.log', "hoy\n");
        touch($viejo, time() - 86400);
        touch($nuevo, time());
        file_put_contents($this->dir . '/logs/laravel-2026-11-17.env', 'APP_KEY=x');
        touch($this->dir . '/logs/laravel-2026-11-17.env', time() + 60);

        $patron = $this->dir . '/logs/laravel-*.log';
        $this->assertNull($this->lector->rechazo($patron));
        $r = $this->lector->leer($patron);
        $this->assertSame("hoy\n", $r['texto']);
        $this->assertSame(realpath($nuevo), $r['actual']);

        $todos = $this->dir . '/logs/laravel-*';
        $this->assertSame(realpath($nuevo), $this->lector->info($todos)['actual'], 'el .env más nuevo se salta');
        $this->assertStringContainsString('Ningún archivo', (string) $this->lector->info($this->dir . '/logs/nada-*.log')['motivo']);
    }

    public function test_un_binario_no_se_pinta(): void
    {
        $ruta = $this->archivo('foto.png', "\x89PNG\r\n\x1a\n\0\0\0\rIHDR" . random_bytes(64));
        $r = $this->lector->leer($ruta);
        $this->assertTrue($r['binario']);
        $this->assertSame('', $r['texto']);

        $config = $this->archivo('pjsip.conf', "[transport-udp]\ntype=transport\n");
        $r = $this->lector->leer($config);
        $this->assertFalse($r['binario']);
        $this->assertSame("[transport-udp]\ntype=transport\n", $r['texto']);
    }

    public function test_lo_muy_grande_no_se_muestra_completo(): void
    {
        // Texto (no ceros: un archivo de puros bytes nulos es binario). Líneas de 1 KB.
        $linea = str_repeat('x', 1023) . "\n";
        $ruta = $this->archivo('grande.log', str_repeat($linea, intdiv(LectorLogs::MAXIMO_COMPLETO, 1024) + 1));

        $r = $this->lector->leer($ruta);
        $this->assertFalse($r['binario']);
        $this->assertSame(LectorLogs::BYTES_POR_PAGINA - 1024, strlen($r['texto']), 'de lo grande se ve lo último, en líneas completas');
        $this->expectException(\LengthException::class);
        $this->lector->leer($ruta, completo: true);
    }
}
