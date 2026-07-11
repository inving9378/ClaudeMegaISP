<?php

namespace App\Modules\Addons\Roadmap\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Provisiona (idempotente) un worktree git dedicado para el ejecutor del Circuito (#334 Fase 0:
 * aislamiento). El ejecutor trabaja en SU worktree, separado del checkout principal
 * (/var/www/megaisp) donde viven las sesiones interactivas de CC → dos CC ya no comparten
 * working tree = mata la colisión de raíz (un `git checkout` del ejecutor ya no mueve el HEAD
 * de una sesión interactiva).
 *
 *   - Crea el worktree si falta: `git worktree add --detach <path> <base>` (detached en el tip
 *     de main; las ramas de item se crean después con `circuito:rama`).
 *   - COPIA `vendor` (no symlink): el autoloader de Composer resuelve su baseDir por `__DIR__`,
 *     y un symlink lo haría apuntar a `/var/www/megaisp/app` → el worktree ejecutaría el código
 *     de MAIN, no el suyo (no podría verificar sus propias ediciones PHP). Copiado (97 MB), el
 *     `App\` PSR-4 resuelve a `<worktree>/app` → aislamiento TOTAL (git + código + verificación).
 *   - Symlinkea `.env` (misma config/DB) y `node_modules` (520 MB; las herramientas JS resuelven
 *     por cwd, el symlink no las confunde) desde el checkout PRINCIPAL.
 *   - `bootstrap/cache` y `storage/` son PROPIOS del worktree (esqueleto escribible) para no pisar
 *     caché/logs del checkout principal.
 *
 * Reusable para Fase 1 (N worktrees): `--path=/home/meganet/circuito/wt-1`, etc.
 * NUNCA hace push ni toca prod. Idempotente: correrlo N veces deja el mismo estado.
 * ⚠️ Si `main` corre `composer install` (cambia deps), re-provisionar con `--resync-vendor`.
 */
class ProvisionWorktreeCommand extends Command
{
    protected $signature = 'circuito:provision-worktree
        {--path= : ruta del worktree (default /home/meganet/circuito/wt-exec)}
        {--base=main : rama/commit base del worktree}
        {--resync-vendor : re-copiar vendor aunque ya exista (tras un composer install en main)}';

    protected $description = 'Provisiona (idempotente) el worktree dedicado del ejecutor del Circuito (aislamiento #334).';

    /** Runtime dir del circuito (alineado con deploy/circuito/vuelta.sh). */
    private const DEFAULT_PATH = '/home/meganet/circuito/wt-exec';

    /** Symlinks al runtime compartido (gitignored → ausentes en un worktree recién creado). */
    private const SHARED_LINKS = ['.env', 'node_modules'];

    /** Directorios PROPIOS del worktree (mkdir escribible; NO compartidos). */
    private const OWN_DIRS = [
        'bootstrap/cache',
        'storage/framework/cache/data',
        'storage/framework/views',
        'storage/framework/sessions',
        'storage/logs',
        'storage/app/public',
    ];

    public function handle(): int
    {
        $path = rtrim((string) ($this->option('path') ?: self::DEFAULT_PATH), '/');
        $base = (string) $this->option('base') ?: 'main';

        // Checkout PRINCIPAL = primer worktree de `git worktree list` (la fuente de los symlinks).
        // Se resuelve así (no por base_path()) para que provisionar DESDE el propio worktree no
        // genere symlinks circulares (vendor -> vendor).
        $main = $this->mainCheckout();
        if ($main === null) {
            $this->error('No pude resolver el checkout principal (git worktree list).');

            return self::FAILURE;
        }
        if ($path === $main) {
            $this->error("El path del worktree ({$path}) es el checkout principal; abortando.");

            return self::FAILURE;
        }

        // 1) Crear el worktree si falta (idempotente).
        if (! $this->isWorktree($path, $main)) {
            if (is_dir($path)) {
                $this->error("El path {$path} existe pero NO es un worktree git; resuélvelo a mano.");

                return self::FAILURE;
            }
            $add = $this->git($main, ['worktree', 'add', '--detach', $path, $base]);
            if (! $add->isSuccessful()) {
                $this->error("No se pudo crear el worktree en {$path}:\n" . $add->getErrorOutput());

                return self::FAILURE;
            }
            $this->info("Worktree creado en {$path} (detached @ {$base}).");
        } else {
            $this->line("Worktree ya existe en {$path}.");
        }

        // 2) vendor COPIADO (no symlink) → el autoloader resuelve App\ a <worktree>/app.
        $vendorDst = $path . '/vendor';
        if (is_link($vendorDst)) {
            @unlink($vendorDst); // limpia un symlink de una corrida vieja
        }
        if (! is_dir($vendorDst) || $this->option('resync-vendor')) {
            $this->line('  copiando vendor (97 MB)…');
            $cp = new Process(['cp', '-a', '--', $main . '/vendor/.', $vendorDst], $path, null, null, 300);
            @mkdir($vendorDst, 0775, true);
            $cp->run();
            if (! $cp->isSuccessful()) {
                $this->error("  no pude copiar vendor:\n" . $cp->getErrorOutput());

                return self::FAILURE;
            }
        } else {
            $this->line('  vendor ya presente (usa --resync-vendor para re-copiar).');
        }

        // 3) Symlinks al runtime compartido (.env, node_modules).
        foreach (self::SHARED_LINKS as $rel) {
            $target = $main . '/' . $rel;
            $link   = $path . '/' . $rel;
            if (! file_exists($target)) {
                $this->warn("  omito {$rel}: no existe en el principal ({$target}).");
                continue;
            }
            if (is_link($link)) {
                @unlink($link);
            } elseif (file_exists($link)) {
                $this->warn("  omito {$rel}: ya existe como archivo/dir real en el worktree.");
                continue;
            }
            @mkdir(dirname($link), 0775, true);
            if (! @symlink($target, $link)) {
                $this->error("  no pude symlinkear {$rel}.");

                return self::FAILURE;
            }
        }

        // 4) Directorios propios del worktree (bootstrap/cache + storage skeleton).
        foreach (self::OWN_DIRS as $rel) {
            @mkdir($path . '/' . $rel, 0775, true);
        }

        $this->info("Provisioning OK: {$path}");

        return self::SUCCESS;
    }

    /** Ruta del checkout principal (primer `worktree` de `git worktree list --porcelain`). */
    private function mainCheckout(): ?string
    {
        $p = new Process(['git', 'worktree', 'list', '--porcelain'], base_path());
        $p->run();
        if (! $p->isSuccessful()) {
            return null;
        }
        foreach (preg_split('/\R/', $p->getOutput()) as $line) {
            if (str_starts_with($line, 'worktree ')) {
                return rtrim(substr($line, strlen('worktree ')), '/');
            }
        }

        return null;
    }

    /** ¿`$path` es un worktree registrado del repo? */
    private function isWorktree(string $path, string $main): bool
    {
        $p = $this->git($main, ['worktree', 'list', '--porcelain']);
        if (! $p->isSuccessful()) {
            return false;
        }
        foreach (preg_split('/\R/', $p->getOutput()) as $line) {
            if ($line === 'worktree ' . $path) {
                return true;
            }
        }

        return false;
    }

    private function git(string $cwd, array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), $cwd);
        $p->run();

        return $p;
    }
}
