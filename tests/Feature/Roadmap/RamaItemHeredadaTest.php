<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Console\RamaItemCommand;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Tests\CreatesApplication;

/**
 * Candado (2026-10-26): los ids de items se reusaron, así que `circuito:rama` encontraba ramas
 * `circuito/item-<id>-*` de un trabajo VIEJO con el mismo número y las reusaba — el merge del item
 * nuevo arrastraba ese trabajo ajeno (#585, #939) y 7 items de UNI-T quedaron pausados.
 *
 * Corre el comando de verdad contra un repo git TEMPORAL (costura `repoDir()`), nunca contra el
 * checkout de este worktree. `DatabaseTransactions` + `CreatesApplication`: rollback al terminar.
 */
class RamaItemHeredadaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private ?string $dir = null;

    protected function tearDown(): void
    {
        if ($this->dir && is_dir($this->dir)) {
            (new Process(['rm', '-rf', $this->dir]))->run();
        }

        parent::tearDown();
    }

    public function test_rama_vieja_con_el_mismo_numero_no_se_reusa(): void
    {
        $item = $this->item();
        $vieja = "circuito/item-{$item->id}-trabajo-viejo";
        $this->repoConRama($vieja, '2025-01-01T10:00:00');

        $this->correr($item);

        $item->refresh();
        $this->assertNotSame($vieja, $item->branch, 'Una rama con commits anteriores al alta no es del item.');
        $this->assertStringStartsWith("circuito/item-{$item->id}-", (string) $item->branch);
        $this->assertSame(
            '',
            trim($this->git(['log', '--format=%H', "main..{$item->branch}"])->getOutput()),
            'La rama nueva sale de main, sin el trabajo viejo.'
        );
        $this->assertTrue($this->git(['rev-parse', '--verify', $vieja])->isSuccessful(), 'La rama vieja no se borra.');
        $this->assertContains('ramas_heredadas_ignoradas', array_column($item->log ?: [], 'evento'));
    }

    public function test_rama_con_trabajo_del_item_se_sigue_reusando(): void
    {
        $item = $this->item();
        $propia = "circuito/item-{$item->id}-trabajo-propio";
        $this->repoConRama($propia, now()->addMinute()->toIso8601String());

        $this->correr($item);

        $item->refresh();
        $this->assertSame($propia, $item->branch);
        $this->assertNotContains('ramas_heredadas_ignoradas', array_column($item->log ?: [], 'evento'));
    }

    private function item(): RoadmapItem
    {
        return RoadmapItem::create([
            'title'             => 'UNI-PRUEBA rama heredada ' . uniqid(),
            'status'            => 'pending',
            'estado_aprobacion' => 'aprobado_irving',
            'nivel_riesgo'      => 'B',
            'modulo'            => 'UNI-PRUEBA',
        ]);
    }

    private function correr(RoadmapItem $item): void
    {
        $dir = $this->dir;
        $cmd = new class($dir) extends RamaItemCommand {
            public function __construct(private string $dirPrueba)
            {
                parent::__construct();
            }

            protected function repoDir(): string
            {
                return $this->dirPrueba;
            }
        };
        $cmd->setLaravel($this->app);
        $out = new BufferedOutput();
        $code = $cmd->run(new ArrayInput(['id' => $item->id]), $out);
        $this->assertSame(0, $code, $out->fetch());
    }

    /** Repo temporal con `main` y una rama del item con un commit fechado (fecha de autor). */
    private function repoConRama(string $rama, string $fechaAutor): void
    {
        $this->dir = sys_get_temp_dir() . '/rama-heredada-' . uniqid();
        mkdir($this->dir);
        $this->git(['init', '-q', '-b', 'main']);
        $this->git(['config', 'user.email', 'prueba@example.test']);
        $this->git(['config', 'user.name', 'Prueba']);
        file_put_contents($this->dir . '/a.txt', "base\n");
        $this->git(['add', 'a.txt']);
        $this->git(['commit', '-q', '-m', 'base']);
        $this->git(['checkout', '-q', '-b', $rama]);
        file_put_contents($this->dir . '/b.txt', "trabajo\n");
        $this->git(['add', 'b.txt']);
        $this->git(['commit', '-q', '-m', 'trabajo'], ['GIT_AUTHOR_DATE' => $fechaAutor]);
        $this->git(['checkout', '-q', 'main']);
    }

    private function git(array $args, array $env = []): Process
    {
        $p = new Process(array_merge(['git'], $args), $this->dir, $env ?: null);
        $p->run();

        return $p;
    }
}
