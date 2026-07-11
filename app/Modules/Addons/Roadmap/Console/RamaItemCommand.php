<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Crea la rama dedicada de un item del Circuito (circuito/item-<id>-<slug>) desde main
 * y la registra en el item (campo branch). El ejecutor trabaja el cambio ahí, aislado
 * de la rama desplegada. NUNCA hace push ni toca prod.
 */
class RamaItemCommand extends Command
{
    protected $signature = 'circuito:rama {id : ID del item}';

    protected $description = 'Crea/checkout la rama del item (circuito/item-<id>-<slug>) desde main y la registra en el item.';

    public function handle(): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');
            return self::FAILURE;
        }

        // #341 (anti-colisión): el circuito NO toma items que un humano/otra sesión ya trabaja
        // (en_progreso o bloqueado). `circuito:rama` es el punto de entrada al trabajo autónomo,
        // así que aquí se corta antes de crear/checar la rama.
        if ($item->estaEnDesarrollo()) {
            $this->error("El item #{$item->id} está EN DESARROLLO (estado {$item->estado_aprobacion}"
                . ($item->en_desarrollo_humano ? ', bloqueado por humano' : '')
                . '). El circuito no puede tomarlo para una vuelta autónoma (candado #341).');
            return self::FAILURE;
        }

        $slug   = Str::slug(Str::limit($item->title, 40, ''));
        $branch = "circuito/item-{$item->id}-{$slug}";

        // ¿ya existe la rama?
        if ($this->git(['rev-parse', '--verify', $branch])->isSuccessful()) {
            $this->git(['checkout', $branch]);
            $this->registrar($item, $branch);
            $this->info("Rama {$branch} ya existía; checkout hecho. Registrada en el item #{$item->id}.");
            return self::SUCCESS;
        }

        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            $this->error('El árbol de trabajo tiene cambios sin commitear; commitea o descarta antes de ramificar.');
            return self::FAILURE;
        }

        // Rama desde el ref `main` SIN checar main. En el modelo de worktree aislado (#334
        // Fase 0) `main` vive checado en el checkout principal (/var/www/megaisp) y git PROHÍBE
        // checarlo en dos worktrees a la vez. `checkout -b X main` crea la rama desde el tip de
        // main y la checa, sin tocar main → funciona tanto en el worktree del ejecutor como en
        // el checkout principal. (Antes: `checkout main` + `checkout -b X`, que rompía en worktree.)
        if (! $this->git(['checkout', '-b', $branch, 'main'])->isSuccessful()) {
            $this->error("No se pudo crear la rama {$branch} desde main.");
            return self::FAILURE;
        }

        $this->registrar($item, $branch);
        $this->info("Rama {$branch} creada desde main y registrada en el item #{$item->id}.");
        return self::SUCCESS;
    }

    private function registrar(RoadmapItem $item, string $branch): void
    {
        $item->branch = $branch;
        $item->save();
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();
        return $p;
    }
}
