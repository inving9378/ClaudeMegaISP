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
 * Verificación en vivo del punto (e) de #9990644 / #9990657: con una rama AJENA checada a mano en
 * el "checkout principal" (simula a Irving/una sesión humana trabajando en /var/www/megaisp),
 * confirma las 3 garantías del item — SIN tocar el checkout real (prohibido por la regla de
 * aislamiento #334 de las terminales del circuito; ver CLAUDE.md). En vez de experimentar sobre
 * /var/www/megaisp, reproduce el MISMO montaje que producción (dos `git worktree` del mismo repo,
 * uno detached en main para el runner y otro con una rama propia para el "humano") sobre un repo
 * temporal DESCARTABLE — mismo patrón que `MergeRunnerAterrizajeEnMainTest`.
 *
 * Confirma:
 *  (1) el HEAD del checkout "principal" (con la rama ajena) NO se mueve ni un commit.
 *  (2) el merge SÍ avanza `main` en el repo compartido (vía `update-ref`, visible desde cualquier
 *      worktree de ese repo, incluido el "principal").
 *  (3) el árbol de trabajo del checkout "principal" queda IDÉNTICO — nada lo resetea ni lo toca.
 */
class MergeRunnerNoTocaCheckoutAjenoTest extends TestCase
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

    public function test_checkout_con_rama_ajena_no_se_toca_y_main_avanza_en_el_repo_compartido(): void
    {
        [$repo, $principal, $workDir] = $this->crearRepoConWorktreesDedicados();

        $mainAntes = trim($this->git($repo, ['rev-parse', 'main'])->getOutput());
        $principalHeadAntes = trim($this->git($principal, ['rev-parse', 'HEAD'])->getOutput());
        $principalRamaAntes = trim($this->git($principal, ['rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());
        $this->assertSame('rama-humana-ajena', $principalRamaAntes, 'Precondición: el checkout "principal" debe estar en SU rama, no en main.');

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9990657 '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'item-branch',
        ]);

        $runner = $this->runner($workDir, $principal);
        $res = $runner->performMerge($item);

        $this->assertTrue($res['ok'] ?? false, 'El merge debía aterrizar limpio: '.($res['salida'] ?? 'sin detalle'));
        $this->assertNotNull($res['merge_commit']);

        // (1) El HEAD del checkout "principal" (rama ajena) NO se movió ni un commit.
        $principalHeadDespues = trim($this->git($principal, ['rev-parse', 'HEAD'])->getOutput());
        $principalRamaDespues = trim($this->git($principal, ['rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());
        $this->assertSame($principalHeadAntes, $principalHeadDespues, 'El checkout con la rama ajena NO debe moverse ni un commit.');
        $this->assertSame('rama-humana-ajena', $principalRamaDespues, 'El checkout ajeno debe seguir en SU rama, nunca adoptar main.');

        // (2) main SÍ avanzó en el repo compartido (visible desde cualquier worktree, incluido el "principal").
        $mainDespues = trim($this->git($repo, ['rev-parse', 'main'])->getOutput());
        $this->assertNotSame($mainAntes, $mainDespues, 'main debe avanzar con el merge.');
        $this->assertSame($res['merge_commit'], $mainDespues);
        $mainVistoDesdePrincipal = trim($this->git($principal, ['rev-parse', 'main'])->getOutput());
        $this->assertSame($mainDespues, $mainVistoDesdePrincipal, 'El avance de main debe ser visible desde el checkout "principal" (mismo repo compartido, refs/heads/main).');

        // (3) El árbol de trabajo del checkout "principal" queda idéntico: nada lo resetea/toca.
        $estado = trim($this->git($principal, ['status', '--porcelain'])->getOutput());
        $this->assertSame('', $estado, 'El checkout ajeno no debe reportar ningún cambio: nada lo tocó.');
        $this->assertFileExists($principal.'/humano.php', 'El archivo propio de la rama ajena debe seguir ahí, intacto.');
        $this->assertFileDoesNotExist($principal.'/feature.php', 'El checkout ajeno NUNCA debe recibir los archivos del merge (no se sincroniza si no está en main).');

        $item->refresh();
        $this->assertSame('completado', $item->estado_aprobacion);
        $this->assertSame($res['merge_commit'], $item->merge_commit);
    }

    /**
     * Repo temporal compartido con DOS worktrees adicionales (mismo patrón que producción):
     *  - $repo: repo temporal con `main` (checado ahí, como cualquier primer worktree de git) +
     *    `item-branch` lista para mergear.
     *  - $principal: worktree con una rama AJENA propia checada — simula /var/www/megaisp con
     *    trabajo humano en curso (nunca el checkout real).
     *  - $workDir: worktree DEDICADO, detached en main — simula el worktree exclusivo del runner.
     */
    private function crearRepoConWorktreesDedicados(): array
    {
        $repo = sys_get_temp_dir().'/mergerunner-repo-'.uniqid();
        mkdir($repo, 0775, true);
        $this->tmpDirs[] = $repo;

        $this->git($repo, ['init', '-q', '-b', 'main']);
        $this->git($repo, ['config', 'user.email', 'test@merge-runner.local']);
        $this->git($repo, ['config', 'user.name', 'MergeRunner Test']);

        file_put_contents($repo.'/base.php', "<?php\n// base\n");
        $this->git($repo, ['add', 'base.php']);
        $this->git($repo, ['commit', '-q', '-m', 'base']);

        $this->git($repo, ['checkout', '-q', '-b', 'item-branch']);
        file_put_contents($repo.'/feature.php', "<?php\n// feature\n");
        $this->git($repo, ['add', 'feature.php']);
        $this->git($repo, ['commit', '-q', '-m', 'feature']);
        $this->git($repo, ['checkout', '-q', 'main']);

        $principal = sys_get_temp_dir().'/mergerunner-principal-'.uniqid();
        $this->tmpDirs[] = $principal;
        $this->git($repo, ['worktree', 'add', '-q', '-b', 'rama-humana-ajena', $principal, 'main']);
        file_put_contents($principal.'/humano.php', "<?php\n// trabajo humano en curso, sin relacion con el merge\n");
        $this->git($principal, ['add', 'humano.php']);
        $this->git($principal, ['commit', '-q', '-m', 'trabajo humano ajeno']);

        $workDir = sys_get_temp_dir().'/mergerunner-workdir-'.uniqid();
        $this->tmpDirs[] = $workDir;
        $this->git($repo, ['worktree', 'add', '-q', '--detach', $workDir, 'main']);

        return [$repo, $principal, $workDir];
    }

    private function runner(string $workDir, string $principal): TestableMergeRunnerConCheckoutPrincipal
    {
        return new TestableMergeRunnerConCheckoutPrincipal($workDir, $principal, new RoadmapCircuitoService(), new JarvisIndiceService());
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
 * worktree "principal" (rama ajena) — ambos DISTINTOS, a diferencia de las demás pruebas de
 * `MergeRunner` donde coinciden a propósito (no-op de sincronización). Aquí sí importa que
 * difieran: es justo lo que reproduce el escenario real de producción.
 */
class TestableMergeRunnerConCheckoutPrincipal extends MergeRunner
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

    /** Evita bootear `php artisan` (el repo temporal no es una app Laravel): stub siempre-OK. */
    protected function regression(): array
    {
        return ['ok' => true, 'detalle' => 'stub de prueba (#9990657)'];
    }
}
