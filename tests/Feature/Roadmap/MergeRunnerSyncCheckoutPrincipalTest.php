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
 * Candado de regresión del item #9991086 (BUG DE ORIGEN, 0 syncs reales desde 2026-09-09): la
 * limpieza del checkout principal se comprobaba con `git status --porcelain`, que compara contra
 * HEAD — pero `update-ref` (en `performMerge()`) ya había avanzado `refs/heads/main` al commit
 * NUEVO antes de llegar a `syncCheckoutPrincipal()`, así que CUALQUIER archivo tocado por el merge
 * se veía "sucio" (índice viejo vs HEAD ya nuevo) y el checkout nunca sincronizaba.
 *
 * Reproduce el mismo montaje que producción (dos `git worktree` del mismo repo: uno detached en
 * main para el runner, otro con `main` checada de verdad para el "checkout principal") sobre un
 * repo temporal descartable — mismo patrón que `MergeRunnerNoTocaCheckoutAjenoTest` — para no
 * tocar ningún checkout real (regla de aislamiento #334).
 */
class MergeRunnerSyncCheckoutPrincipalTest extends TestCase
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
     * No-regresión / caso sano: checkout principal en `main`, limpio, en el sha viejo. Tras el
     * merge debe quedar sincronizado de verdad (reset --hard aplicado, archivos nuevos presentes).
     */
    public function test_checkout_principal_limpio_se_sincroniza_tras_el_merge(): void
    {
        [$principal, $workDir] = $this->crearRepoConWorktreesDedicados();

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9991086 (checkout limpio) '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'item-branch',
        ]);

        $runner = $this->runner($workDir, $principal);
        $res = $runner->performMerge($item);

        $this->assertTrue($res['ok'] ?? false, 'El merge debía aterrizar limpio: '.($res['salida'] ?? 'sin detalle'));
        $this->assertNotNull($res['merge_commit']);

        // El checkout principal debe haber quedado sincronizado: HEAD al día y el archivo nuevo presente.
        $principalHeadDespues = trim($this->git($principal, ['rev-parse', 'HEAD'])->getOutput());
        $this->assertSame($res['merge_commit'], $principalHeadDespues, 'El checkout principal limpio debe sincronizarse al commit del merge.');
        $this->assertFileExists($principal.'/feature.php', 'El archivo que trajo el merge debe aparecer en el checkout principal ya sincronizado.');

        $estado = trim($this->git($principal, ['status', '--porcelain'])->getOutput());
        $this->assertSame('', $estado, 'Tras sincronizar, el checkout principal debe quedar limpio.');
    }

    /**
     * El bug real: un archivo modificado A MANO (sin commitear, sin relación con el merge) en el
     * checkout principal debe seguir bloqueando el sync — pero por ser trabajo humano de verdad,
     * NO por el falso positivo de comparar contra el HEAD ya avanzado.
     */
    public function test_checkout_principal_con_archivo_modificado_a_mano_no_se_sincroniza(): void
    {
        [$principal, $workDir] = $this->crearRepoConWorktreesDedicados();

        // Trabajo humano sin commitear en el checkout principal: modifica un archivo YA trackeado.
        file_put_contents($principal.'/base.php', "<?php\n// base\n// editado a mano, sin commitear\n");

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9991086 (checkout sucio) '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'item-branch',
        ]);

        $runner = $this->runner($workDir, $principal);
        $res = $runner->performMerge($item);

        // El merge en sí debe seguir aterrizando bien en el repo compartido — el sync del checkout
        // principal es best-effort y un fallo ahí nunca debe revertir/bloquear el merge.
        $this->assertTrue($res['ok'] ?? false, 'El merge debía aterrizar limpio: '.($res['salida'] ?? 'sin detalle'));
        $this->assertNotNull($res['merge_commit']);

        // Pero el checkout principal NO se toca: la edición humana sigue intacta y el archivo nuevo
        // del merge nunca se materializó ahí (nada corrió `reset --hard`).
        $this->assertStringContainsString(
            'editado a mano, sin commitear',
            file_get_contents($principal.'/base.php'),
            'El archivo editado a mano debe seguir intacto: el sync debe omitirse, no pisarlo.'
        );
        $this->assertFileDoesNotExist($principal.'/feature.php', 'Sin sync, el archivo del merge nunca debe aparecer en el checkout principal.');

        $item->refresh();
        $this->assertSame('completado', $item->estado_aprobacion, 'El item se marca integrado aunque el sync del checkout principal se haya omitido (best-effort).');
    }

    /**
     * Mismo montaje que producción: $principal es el checkout ORIGINAL (como /var/www/megaisp, con
     * `main` checada de verdad ahí mismo — git no permite la misma rama checada en dos worktrees a
     * la vez, así que NO puede ser un `git worktree add` como en `MergeRunnerNoTocaCheckoutAjenoTest`)
     * y $workDir es un worktree SECUNDARIO detached en main, colgado de ese mismo repo — como el
     * worktree exclusivo del runner (`MERGE_WORKTREE`).
     */
    private function crearRepoConWorktreesDedicados(): array
    {
        $principal = sys_get_temp_dir().'/mergerunner-principal-'.uniqid();
        mkdir($principal, 0775, true);
        $this->tmpDirs[] = $principal;

        $this->git($principal, ['init', '-q', '-b', 'main']);
        $this->git($principal, ['config', 'user.email', 'test@merge-runner.local']);
        $this->git($principal, ['config', 'user.name', 'MergeRunner Test']);

        file_put_contents($principal.'/base.php', "<?php\n// base\n");
        $this->git($principal, ['add', 'base.php']);
        $this->git($principal, ['commit', '-q', '-m', 'base']);

        $this->git($principal, ['checkout', '-q', '-b', 'item-branch']);
        file_put_contents($principal.'/feature.php', "<?php\n// feature\n");
        $this->git($principal, ['add', 'feature.php']);
        $this->git($principal, ['commit', '-q', '-m', 'feature']);
        $this->git($principal, ['checkout', '-q', 'main']);

        $workDir = sys_get_temp_dir().'/mergerunner-workdir-'.uniqid();
        $this->tmpDirs[] = $workDir;
        $this->git($principal, ['worktree', 'add', '-q', '--detach', $workDir, 'main']);

        return [$principal, $workDir];
    }

    private function runner(string $workDir, string $principal): TestableMergeRunnerSyncCheckoutPrincipal
    {
        return new TestableMergeRunnerSyncCheckoutPrincipal($workDir, $principal, new RoadmapCircuitoService(), new JarvisIndiceService());
    }

    private function git(string $dir, array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), $dir);
        $p->setTimeout(30);
        $p->run();
        $this->assertTrue($p->isSuccessful(), 'git '.implode(' ', $args).' falló: '.$p->getErrorOutput());

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
 * Subclase SOLO de prueba: fija `workDir()` al worktree dedicado y `checkoutPrincipalPath()` al
 * worktree "principal" (con `main` REALMENTE checada, a diferencia de `MergeRunnerAterrizajeEnMainTest`
 * donde ambos coinciden a propósito) + evita bootear `php artisan` (el repo temporal no es una app
 * Laravel real).
 */
class TestableMergeRunnerSyncCheckoutPrincipal extends MergeRunner
{
    public function __construct(
        private string $workDirPath,
        private string $principalPath,
        RoadmapCircuitoService $svc,
        JarvisIndiceService $jarvisIndice
    ) {
        parent::__construct($svc, $jarvisIndice);
    }

    protected function workDir(): string
    {
        return $this->workDirPath;
    }

    protected function checkoutPrincipalPath(): string
    {
        return $this->principalPath;
    }

    protected function regression(): array
    {
        return ['ok' => true, 'detalle' => 'stub de prueba (#9991086)'];
    }
}
