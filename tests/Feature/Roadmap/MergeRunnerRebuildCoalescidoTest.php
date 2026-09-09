<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisIndiceService;
use App\Modules\Addons\Roadmap\Services\MergeRunner;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;
use Tests\CreatesApplication;

/**
 * Candado del item #9990466 (MEDIDO 2026-09-07): el scheduler quedó ~20min sin despachar (0/6
 * terminales) porque `MergeRunner::drain()` recompilaba el bundle (`npm run prod`, ~4min) POR
 * CADA merge de la cola, síncrono, DENTRO de `scheduler.lock`. Con 5 merges en cola = ~20min de
 * candado tomado y cero despacho, aunque hubiera items ejecutables esperando slot.
 *
 * Corre `drain()`/`performMerge()` DE VERDAD (git real) contra un repo temporal aislado — mismo
 * patrón que `MergeRunnerAterrizajeEnMainTest` — con 3 ramas que tocan un `.vue` cada una (para
 * que `clasificarUi()` las marque `ui=true` y dispare la señal de rebuild), y verifica las DOS
 * mitades del fix sin lanzar ningún proceso real de npm:
 *   1. COALESCER — el rebuild se dispara UNA sola vez tras drenar los 3 merges, no una por merge.
 *   2. FUERA DEL LOCK — el rebuild se dispara con `MergeRunner::LOCK` (merge.lock) YA LIBERADO,
 *      es decir DESPUÉS de que `drain()` soltó el candado que serializa el drenado — el mismo
 *      candado que el scheduler no debe esperar mientras el bundle compila.
 */
class MergeRunnerRebuildCoalescidoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->borrarRecursivo($dir);
        }
        $this->tmpDirs = [];

        parent::tearDown();
    }

    public function test_drena_tres_merges_de_frontend_y_dispara_el_rebuild_una_sola_vez(): void
    {
        $dir = $this->crearRepoConTresRamasFrontend();
        $svc = new RoadmapCircuitoService();

        foreach (['rama-a', 'rama-b', 'rama-c'] as $branch) {
            $item = RoadmapItem::create([
                'title' => "Item de prueba #9990466 {$branch} ".uniqid(),
                'status' => 'pending',
                'estado_aprobacion' => 'en_progreso',
                'branch' => $branch,
            ]);
            $svc->enqueueMerge($item->id, 'test', 'test');
        }

        $runner = $this->runner($dir, $svc);
        $res = $runner->drain();

        $this->assertNotNull($res, 'drain() debió tomar el lock (nadie más lo sostiene en el test).');
        $this->assertCount(3, $res, 'Los 3 merges encolados debieron procesarse.');
        foreach ($res as $r) {
            $this->assertTrue($r['ok'] ?? false, 'Fallo inesperado: '.($r['salida'] ?? '?'));
            $this->assertTrue($r['necesita_rebuild'] ?? false, 'Cada merge que toca un .vue debe señalar necesita_rebuild.');
        }

        $this->assertSame(
            1,
            $runner->rebuildLlamado,
            'El rebuild debía dispararse UNA sola vez tras drenar los 3 merges, no una por merge.'
        );
        $this->assertTrue(
            $runner->lockLibreAlDisparar,
            'El rebuild debía dispararse con merge.lock YA LIBERADO — fuera del candado que sostiene el scheduler.'
        );
    }

    /** Repo temporal: main + 3 ramas, cada una agrega su propio .vue (sin choque entre sí). */
    private function crearRepoConTresRamasFrontend(): string
    {
        $dir = sys_get_temp_dir().'/mergerunner-rebuild-test-'.uniqid();
        mkdir($dir, 0775, true);
        $this->tmpDirs[] = $dir;

        $this->git($dir, ['init', '-q', '-b', 'main']);
        $this->git($dir, ['config', 'user.email', 'test@merge-runner.local']);
        $this->git($dir, ['config', 'user.name', 'MergeRunner Test']);

        file_put_contents($dir.'/base.php', "<?php\n// base\n");
        $this->git($dir, ['add', 'base.php']);
        $this->git($dir, ['commit', '-q', '-m', 'base']);

        foreach (['rama-a' => 'A.vue', 'rama-b' => 'B.vue', 'rama-c' => 'C.vue'] as $branch => $file) {
            $this->git($dir, ['checkout', '-q', 'main']);
            $this->git($dir, ['checkout', '-q', '-b', $branch]);
            file_put_contents($dir.'/'.$file, "<template><div>{$file}</div></template>\n");
            $this->git($dir, ['add', $file]);
            $this->git($dir, ['commit', '-q', '-m', "feature {$file}"]);
        }
        $this->git($dir, ['checkout', '-q', 'main']);

        return $dir;
    }

    private function runner(string $dir, RoadmapCircuitoService $svc): TestableRebuildMergeRunner
    {
        return new TestableRebuildMergeRunner($dir, $svc, new JarvisIndiceService());
    }

    private function git(string $dir, array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), $dir);
        $p->setTimeout(30);
        $p->run();
        $this->assertTrue($p->isSuccessful(), "git ".implode(' ', $args)." falló: ".$p->getErrorOutput());

        return $p;
    }

    private function borrarRecursivo(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $it) {
            $it->isDir() ? @rmdir($it->getPathname()) : @unlink($it->getPathname());
        }
        @rmdir($dir);
    }
}

/**
 * Subclase SOLO de prueba (#9990466): cuenta cuántas veces se dispararía el rebuild y verifica
 * que, en ESE momento, el candado `MergeRunner::LOCK` (merge.lock) ya está libre — sin lanzar
 * ningún proceso real de npm ni tocar el candado real fuera de esta aserción de lectura.
 */
class TestableRebuildMergeRunner extends MergeRunner
{
    public int $rebuildLlamado = 0;
    public bool $lockLibreAlDisparar = false;

    public function __construct(private string $dir, RoadmapCircuitoService $svc, JarvisIndiceService $jarvisIndice)
    {
        parent::__construct($svc, $jarvisIndice);
    }

    protected function workDir(): string
    {
        return $this->dir;
    }

    /** #9990644 — mismo path que workDir(): syncCheckoutPrincipal() se autodesactiva (no-op). */
    protected function checkoutPrincipalPath(): string
    {
        return $this->dir;
    }

    protected function regression(): array
    {
        return ['ok' => true, 'detalle' => 'stub de prueba (#9990466)'];
    }

    protected function triggerRebuildAsync(): void
    {
        $this->rebuildLlamado++;

        // Si drain() ya soltó merge.lock (finally, antes de llamar aquí), este flock
        // no-bloqueante debe conseguirlo de inmediato — sin esperar ni competir con nadie.
        $lock = @fopen(self::LOCK, 'c');
        $this->lockLibreAlDisparar = (bool) ($lock && flock($lock, LOCK_EX | LOCK_NB));
        if ($lock) {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
