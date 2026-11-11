<?php

use App\Services\Deploy\CommitsRetenidos;
use PHPUnit\Framework\TestCase;

class CommitsRetenidosTest extends TestCase
{
    private string $repo;
    private string $git;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/retenidos_' . uniqid();
        mkdir($this->repo);
        $this->git = 'git -C ' . escapeshellarg($this->repo) . ' -c user.name=t -c user.email=t@t ';
        shell_exec("git -C {$this->repo} init -q -b main 2>&1");
        $this->commit('base.txt', 'base');
        shell_exec($this->git . 'tag V1');
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->repo));
    }

    private function commit(string $archivo, string $contenido): string
    {
        file_put_contents("{$this->repo}/{$archivo}", $contenido . "\n");
        shell_exec($this->git . 'add ' . escapeshellarg($archivo));
        shell_exec($this->git . 'commit -q -m ' . escapeshellarg($contenido));

        return trim((string) shell_exec($this->git . 'rev-parse HEAD'));
    }

    private function retenido(string $hash): array
    {
        return [['hash' => $hash, 'item' => '#1', 'motivo' => 'prueba']];
    }

    public function test_lo_detecta_en_la_historia(): void
    {
        $hash = $this->commit('a.txt', 'retenido');
        $this->commit('b.txt', 'despues');

        $this->assertSame('historia', CommitsRetenidos::en($this->repo, 'HEAD', $this->retenido($hash))[0]['via']);
    }

    public function test_lo_detecta_traido_por_cherry_pick(): void
    {
        $hash = $this->commit('a.txt', 'retenido');
        shell_exec($this->git . 'switch -q -c release V1');
        $this->commit('r.txt', 'otro arreglo');
        shell_exec($this->git . 'cherry-pick ' . $hash . ' 2>&1');
        $copia = trim((string) shell_exec($this->git . 'rev-parse HEAD'));

        $this->assertNotSame($hash, $copia);
        $this->assertSame('cherry-pick', CommitsRetenidos::en($this->repo, 'HEAD', $this->retenido($hash))[0]['via']);
    }

    public function test_una_version_sin_el_commit_pasa(): void
    {
        $hash = $this->commit('a.txt', 'retenido');
        shell_exec($this->git . 'switch -q -c release V1');
        $this->commit('c.txt', 'otro arreglo');
        shell_exec($this->git . 'tag V2');

        $this->assertSame([], CommitsRetenidos::en($this->repo, 'V2', $this->retenido($hash)));
    }

    public function test_si_no_puede_evaluar_detiene(): void
    {
        $falso = str_repeat('a', 40);

        $this->assertSame('no se pudo evaluar', CommitsRetenidos::en($this->repo, 'HEAD', $this->retenido($falso))[0]['via']);
    }

    public function test_un_ref_inexistente_lo_resuelve_el_guard_de_cada_puerta(): void
    {
        $this->assertSame([], CommitsRetenidos::en($this->repo, 'V9', $this->retenido(str_repeat('a', 40))));
    }

    public function test_lee_la_lista_e_ignora_comentarios(): void
    {
        $archivo = "{$this->repo}/lista.txt";
        file_put_contents($archivo, "# comentario\n\nabc123 #7 falta validar esto\n");

        $this->assertSame([['hash' => 'abc123', 'item' => '#7', 'motivo' => 'falta validar esto']], CommitsRetenidos::lista($archivo));
        $this->assertSame([], CommitsRetenidos::lista("{$this->repo}/no-existe.txt"));
    }

    private function versionDeUnSoloCommit(): void
    {
        $arbol = trim((string) shell_exec($this->git . 'rev-parse HEAD^{tree}'));
        $unico = trim((string) shell_exec($this->git . 'commit-tree ' . $arbol . ' -p V1 -m version 2>&1'));
        shell_exec($this->git . 'tag V2 ' . $unico);
    }

    public function test_lo_detecta_en_una_version_de_un_solo_commit(): void
    {
        $hash = $this->commit('a.txt', 'contenido retenido largo');
        $this->commit('b.txt', 'otro cambio posterior');
        $this->versionDeUnSoloCommit();

        $this->assertSame('contenido', CommitsRetenidos::en($this->repo, 'V2', $this->retenido($hash))[0]['via']);
    }

    public function test_version_de_un_solo_commit_sin_el_retenido_pasa(): void
    {
        $hash = $this->commit('a.txt', 'contenido retenido largo');
        shell_exec($this->git . 'switch -q -c release V1');
        $this->commit('r.txt', 'arreglo ajeno sin relacion');
        $this->versionDeUnSoloCommit();

        $this->assertSame([], CommitsRetenidos::en($this->repo, 'V2', $this->retenido($hash)));
    }
}
