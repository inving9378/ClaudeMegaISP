<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisIndiceService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;
use Tests\CreatesApplication;

require_once __DIR__.'/MergeRunnerAterrizajeEnMainTest.php';

/** #1013 — rama ya contenida en main: no anotar HEAD si la rama no trae trabajo del item. */
class MergeRunnerRamaYaEnMainTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mergerunner-1013-'.uniqid();
        mkdir($this->dir, 0775, true);
        $this->git(['init', '-q', '-b', 'main']);
        $this->git(['config', 'user.email', 't@t.local']);
        $this->git(['config', 'user.name', 'T']);
        file_put_contents($this->dir.'/a', 'a');
        $this->git(['add', 'a']);
        $this->commit('base', '2020-01-01T00:00:00');
        $this->git(['branch', 'heredada']);
        file_put_contents($this->dir.'/b', 'b');
        $this->git(['add', 'b']);
        $this->commit('trabajo propio', '2099-01-01T00:00:00');
        $this->git(['branch', 'propia']);
        file_put_contents($this->dir.'/c', 'c');
        $this->git(['add', 'c']);
        $this->commit('merge de otro item', '2099-01-02T00:00:00');
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();
        parent::tearDown();
    }

    public function test_rama_heredada_sin_trabajo_no_marca_ni_anota_head(): void
    {
        $item = $this->item('heredada');
        $res = $this->runner()->performMerge($item);

        $this->assertFalse($res['ok']);
        $this->assertNull($res['merge_commit']);
        $item->refresh();
        $this->assertNull($item->merge_commit);
        $this->assertNotSame('completado', $item->estado_aprobacion);
        $this->assertSame('merge_sin_trabajo_propio', collect($item->log)->last()['evento']);
    }

    public function test_rama_con_trabajo_propio_ya_en_main_anota_la_punta(): void
    {
        $item = $this->item('propia');
        $res = $this->runner()->performMerge($item);

        $punta = trim($this->git(['rev-parse', 'propia'])->getOutput());
        $this->assertTrue($res['ok']);
        $this->assertSame($punta, $res['merge_commit']);
        $this->assertNotSame(trim($this->git(['rev-parse', 'HEAD'])->getOutput()), $res['merge_commit']);
    }

    private function item(string $branch): RoadmapItem
    {
        return RoadmapItem::create([
            'title' => 'Item prueba #1013 '.uniqid(), 'status' => 'pending',
            'estado_aprobacion' => 'en_progreso', 'branch' => $branch,
        ]);
    }

    private function runner(): TestableMergeRunner
    {
        return new TestableMergeRunner($this->dir, new RoadmapCircuitoService(), new JarvisIndiceService());
    }

    private function commit(string $msg, string $fecha): void
    {
        $p = new Process(['git', 'commit', '-q', '-m', $msg], $this->dir, ['GIT_AUTHOR_DATE' => $fecha, 'GIT_COMMITTER_DATE' => $fecha]);
        $p->run();
        $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), $this->dir);
        $p->run();
        $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());

        return $p;
    }
}
