<?php

namespace Tests\Unit\Circuito;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * #502: candado de exclusividad por worktree (deploy/circuito/candado-worktree.sh, hook PreToolUse).
 * Repo git desechable en el directorio temporal: un principal (exento) y dos worktrees. Sin Laravel
 * ni base de datos.
 */
class CandadoWorktreeTest extends TestCase
{
    private string $dir;

    private string $script;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['git', 'jq', 'flock'] as $bin) {
            if (trim((string) shell_exec('command -v ' . $bin)) === '') {
                $this->markTestSkipped("Falta {$bin}");
            }
        }
        $this->script = dirname(__DIR__, 3) . '/deploy/circuito/candado-worktree.sh';
        $this->dir = sys_get_temp_dir() . '/candado502-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/principal/storage/logs', 0775, true);
        $this->sh("cd {$this->dir}/principal && git init -q && git -c user.email=t@example.test -c user.name=t commit -q --allow-empty -m i"
            . " && git worktree add -q --detach {$this->dir}/wt-a && git worktree add -q --detach {$this->dir}/wt-b");
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && str_contains($this->dir, '/candado502-')) {
            $this->sh('rm -rf ' . escapeshellarg($this->dir));
        }
        parent::tearDown();
    }

    private function sh(string $cmd): void
    {
        (new Process(['bash', '-c', $cmd]))->mustRun();
    }

    /** Corre el hook y devuelve [exit, stderr]. */
    private function hook(string $sesion, string $herramienta, string $valor): array
    {
        $entrada = ['session_id' => $sesion, 'tool_name' => $herramienta, 'cwd' => '/',
            'tool_input' => $herramienta === 'Bash' ? ['command' => $valor] : ['file_path' => $valor]];
        $p = new Process(['bash', $this->script, 'hook'], null, ['CANDADO_WORKTREE_EXENTOS' => '/nada/']);
        $p->setInput(json_encode($entrada));
        $p->run();

        return [$p->getExitCode(), $p->getErrorOutput()];
    }

    private function candado(string $wt): ?array
    {
        $f = "{$this->dir}/principal/.git/worktrees/{$wt}/sesion-claude.json";

        return is_file($f) ? json_decode(file_get_contents($f), true) : null;
    }

    public function test_bloquea_a_otra_sesion_y_deja_pasar_lecturas(): void
    {
        $this->assertSame(0, $this->hook('A', 'Edit', "{$this->dir}/wt-a/x.php")[0]);
        $this->assertSame('A', $this->candado('wt-a')['sesion']);

        [$exit, $err] = $this->hook('B', 'Bash', "cd {$this->dir}/wt-a && git checkout -b otra");
        $this->assertSame(2, $exit);
        $this->assertStringContainsString('provision-worktree --sesion=', $err);

        $this->assertSame(2, $this->hook('B', 'Write', "{$this->dir}/wt-a/nuevo/y.php")[0]);
        $this->assertSame(0, $this->hook('B', 'Bash', "cd {$this->dir}/wt-a && git status && git stash list && git log -1")[0]);
        $this->assertSame(0, $this->hook('A', 'Bash', "git -C {$this->dir}/wt-a commit -m x")[0], 'la dueña sigue');
        $this->assertSame(0, $this->hook('B', 'Edit', "{$this->dir}/wt-b/z.php")[0], 'otro worktree, libre');
    }

    public function test_el_principal_queda_fuera(): void
    {
        $this->hook('A', 'Edit', "{$this->dir}/principal/x.php");
        $this->assertSame(0, $this->hook('B', 'Bash', "cd {$this->dir}/principal && git merge algo")[0]);
        $this->assertFalse(is_file("{$this->dir}/principal/.git/sesion-claude.json"));
    }

    public function test_candado_vencido_por_proceso_terminado_o_inactividad(): void
    {
        $this->hook('A', 'Edit', "{$this->dir}/wt-a/x.php");
        $f = "{$this->dir}/principal/.git/worktrees/wt-a/sesion-claude.json";

        file_put_contents($f, json_encode(['pid' => 999999999] + $this->candado('wt-a')));
        $this->assertSame(0, $this->hook('B', 'Edit', "{$this->dir}/wt-a/x.php")[0]);
        $this->assertSame('B', $this->candado('wt-a')['sesion']);

        file_put_contents($f, json_encode(['ultimo' => time() - 4 * 3600] + $this->candado('wt-a')));
        $this->assertSame(0, $this->hook('A', 'Edit', "{$this->dir}/wt-a/x.php")[0]);
        $this->assertSame('A', $this->candado('wt-a')['sesion']);
    }

    public function test_fin_de_sesion_suelta_y_entrada_rara_no_bloquea(): void
    {
        $this->hook('A', 'Edit', "{$this->dir}/wt-a/x.php");
        $this->hook('B', 'Edit', "{$this->dir}/wt-b/x.php");

        $p = new Process(['bash', $this->script, 'fin'], null, ['CLAUDE_PROJECT_DIR' => "{$this->dir}/principal"]);
        $p->setInput(json_encode(['session_id' => 'A']));
        $p->mustRun();
        $this->assertNull($this->candado('wt-a'));
        $this->assertSame('B', $this->candado('wt-b')['sesion']);

        $p = new Process(['bash', $this->script, 'hook']);
        $p->setInput('esto no es json');
        $p->run();
        $this->assertSame(0, $p->getExitCode(), 'falla abierto');
    }
}
