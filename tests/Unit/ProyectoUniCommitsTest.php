<?php

use App\Services\Deploy\ProyectoUniCommits;
use PHPUnit\Framework\TestCase;

class ProyectoUniCommitsTest extends TestCase
{
    public function test_detecta_la_linea_del_proyecto(): void
    {
        $this->assertSame(1, ProyectoUniCommits::contar(["Arregla algo\n\nProyecto-UNI: #863\n", 'Otro commit']));
        $this->assertSame(0, ProyectoUniCommits::contar(['Menciona Proyecto-UNI en prosa', 'Fix']));
    }

    public function test_rango_git_temporal(): void
    {
        $d = sys_get_temp_dir() . '/uni_' . uniqid();
        mkdir($d);
        $g = 'git -C ' . escapeshellarg($d) . ' -c user.name=t -c user.email=t@t ';
        shell_exec("git -C $d init -q 2>&1");
        shell_exec($g . 'commit -q --allow-empty -m base');
        shell_exec($g . 'tag V1');
        shell_exec($g . 'commit -q --allow-empty -m limpio');
        $this->assertSame(0, ProyectoUniCommits::contar(ProyectoUniCommits::mensajesEnRango($d, ProyectoUniCommits::ultimoTag($d))));
        shell_exec($g . 'commit -q --allow-empty -m "cambio" -m "Proyecto-UNI: #863"');
        $this->assertSame(1, ProyectoUniCommits::contar(ProyectoUniCommits::mensajesEnRango($d, 'V1')));
        shell_exec('rm -rf ' . escapeshellarg($d));
    }
}
