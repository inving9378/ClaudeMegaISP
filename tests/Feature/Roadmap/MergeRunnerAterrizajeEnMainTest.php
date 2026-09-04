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
 * Candado del item #9990345 (INCIDENTE REAL 2026-09-04): tres items quedaron `completado` con
 * `merge_commit` apuntando a commits que NUNCA llegaron a `main` — el checkout principal es
 * COMPARTIDO y otro proceso (una sesión interactiva) movió HEAD a otra rama justo antes de que
 * `MergeRunner::performMerge()` commiteara el merge, así que el commit resultante quedó colgado de
 * ESA rama en vez de main, aunque el runner reportó éxito.
 *
 * Corre `performMerge()` de verdad (git real), pero contra un repo temporal AISLADO — nunca contra
 * el checkout de este worktree — vía dos seams puramente de prueba, sin efecto en producción:
 *   · `workDir()` sobreescrito → apunta al repo temporal en vez de `base_path()`.
 *   · `regression()` sobreescrito → evita bootear `php artisan` (el repo temporal no es una app
 *     Laravel real); la lógica bajo prueba es la verificación de aterrizaje, no la de regresión.
 *   · `antesDeCommitParaPruebas()` → hook no-op en producción que este test usa para simular, en el
 *     punto exacto donde ocurrió el incidente, que otro proceso movió HEAD a otra rama.
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar cada test), mismo patrón
 * que `DependenciaGateDespachoTest`/`SubItemCommandDependeDeTest` — evita el `migrate:fresh --seed`
 * de `Tests\TestCase` y no ensucia la BD compartida de dev.
 */
class MergeRunnerAterrizajeEnMainTest extends TestCase
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

    /**
     * El incidente real: HEAD termina en otra rama justo antes del commit final → el merge se
     * commitea con éxito, pero NO queda alcanzable desde main. `performMerge()` debe rechazarlo:
     * NO marcar el item integrado, NO tocar `merge_commit`, y devolver un fallo escalable.
     */
    public function test_no_marca_integrado_si_head_se_mueve_antes_del_commit_final(): void
    {
        $dir = $this->crearRepoConRamaInteractiva();
        $mainAntes = $this->git($dir, ['rev-parse', 'main'])->getOutput();

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9990345 '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'item-branch',
        ]);

        $runner = $this->runner($dir);
        $runner->interferencia = function () use ($dir) {
            // Simula la sesión interactiva que se cruzó en el incidente real: mueve HEAD a otra
            // rama MIENTRAS el merge está en curso (staged, sin commitear todavía).
            $this->git($dir, ['checkout', 'rama-interactiva']);
        };

        $res = $runner->performMerge($item);

        $this->assertFalse($res['ok'], 'El merge debía reportarse como fallido: aterrizó fuera de main.');
        $this->assertTrue($res['escalado'] ?? false, 'Un aterrizaje fuera de main debe ser escalable, no silencioso.');
        $this->assertNull($res['merge_commit']);
        $this->assertStringContainsString('NO quedó en main', $res['salida']);

        $item->refresh();
        $this->assertNull($item->merge_commit, 'El item NUNCA debe marcarse integrado si el commit no llegó a main.');
        $this->assertNotSame('completado', $item->estado_aprobacion);

        $eventos = array_column($item->log ?: [], 'evento');
        $this->assertContains(
            'merge_no_aterrizo_en_main',
            $eventos,
            'Falta el registro en el log del item con la rama real donde quedó HEAD (diagnóstico sin reconstruir el grafo a mano).'
        );

        $entrada = collect($item->log)->firstWhere('evento', 'merge_no_aterrizo_en_main');
        $this->assertSame('rama-interactiva', $entrada['head_branch_real'] ?? null);
        $this->assertSame('item-branch', $entrada['branch'] ?? null);

        // main no avanzó ni un commit: el fallo no dejó rastro en la rama que el circuito protege.
        $mainDespues = $this->git($dir, ['rev-parse', 'main'])->getOutput();
        $this->assertSame($mainAntes, $mainDespues, 'main no debe moverse cuando el merge no aterriza ahí.');
    }

    /**
     * No-regresión: un merge normal (sin interferencia, HEAD en main de punta a punta) debe seguir
     * comportándose EXACTAMENTE igual que antes del candado — mismo merge_commit, mismo item
     * marcado `completado`, mismo mensaje de éxito.
     */
    public function test_un_merge_normal_sigue_integrando_igual_que_antes(): void
    {
        $dir = $this->crearRepoConRamaInteractiva();

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9990345 (no-regresión) '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'item-branch',
        ]);

        $runner = $this->runner($dir); // sin interferencia: antesDeCommitParaPruebas() no hace nada.
        $res = $runner->performMerge($item);

        $this->assertTrue($res['ok'], $res['salida'] ?? 'fallo inesperado');
        $this->assertFalse($res['escalado'] ?? true);
        $this->assertNotNull($res['merge_commit']);
        $this->assertStringContainsString('Integrada a dev', $res['salida']);

        $item->refresh();
        $this->assertSame($res['merge_commit'], $item->merge_commit);
        $this->assertSame('completado', $item->estado_aprobacion);

        // El commit sí quedó alcanzable desde main (el caso sano).
        $contiene = $this->git($dir, ['branch', '--contains', $item->merge_commit, '--list', 'main'])->getOutput();
        $this->assertStringContainsString('main', $contiene);
    }

    /** Repo temporal: main + item-branch (a mergear) + rama-interactiva (archivo distinto, sin choque). */
    private function crearRepoConRamaInteractiva(): string
    {
        $dir = sys_get_temp_dir().'/mergerunner-test-'.uniqid();
        mkdir($dir, 0775, true);
        $this->tmpDirs[] = $dir;

        $this->git($dir, ['init', '-q', '-b', 'main']);
        $this->git($dir, ['config', 'user.email', 'test@merge-runner.local']);
        $this->git($dir, ['config', 'user.name', 'MergeRunner Test']);

        file_put_contents($dir.'/base.php', "<?php\n// base\n");
        $this->git($dir, ['add', 'base.php']);
        $this->git($dir, ['commit', '-q', '-m', 'base']);

        $this->git($dir, ['checkout', '-q', '-b', 'item-branch']);
        file_put_contents($dir.'/feature.php', "<?php\n// feature\n");
        $this->git($dir, ['add', 'feature.php']);
        $this->git($dir, ['commit', '-q', '-m', 'feature']);

        $this->git($dir, ['checkout', '-q', 'main']);
        $this->git($dir, ['checkout', '-q', '-b', 'rama-interactiva']);
        file_put_contents($dir.'/otro.php', "<?php\n// archivo distinto, sin choque con feature.php\n");
        $this->git($dir, ['add', 'otro.php']);
        $this->git($dir, ['commit', '-q', '-m', 'trabajo interactivo no relacionado']);

        $this->git($dir, ['checkout', '-q', 'main']);

        return $dir;
    }

    private function runner(string $dir): TestableMergeRunner
    {
        return new TestableMergeRunner($dir, new RoadmapCircuitoService(), new JarvisIndiceService());
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
 * Subclase SOLO de prueba: expone los 3 seams aditivos de `MergeRunner` (#9990345) sin cambiar
 * ninguna semántica del merge real — en producción esos seams son no-op / devuelven `base_path()`.
 */
class TestableMergeRunner extends MergeRunner
{
    /** @var callable|null */
    public $interferencia = null;

    public function __construct(private string $dir, RoadmapCircuitoService $svc, JarvisIndiceService $jarvisIndice)
    {
        parent::__construct($svc, $jarvisIndice);
    }

    protected function workDir(): string
    {
        return $this->dir;
    }

    /** Evita bootear `php artisan` (el repo temporal no es una app Laravel): stub siempre-OK. */
    protected function regression(): array
    {
        return ['ok' => true, 'detalle' => 'stub de prueba (#9990345)'];
    }

    protected function antesDeCommitParaPruebas(): void
    {
        if ($this->interferencia) {
            ($this->interferencia)();
        }
    }
}
