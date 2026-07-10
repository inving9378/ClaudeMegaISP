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

        $slug   = Str::slug(Str::limit($item->title, 40, ''));
        $branch = "circuito/item-{$item->id}-{$slug}";

        // ¿ya existe la rama?
        if ($this->git(['rev-parse', '--verify', $branch])->isSuccessful()) {
            $this->git(['checkout', $branch]);
            $this->registrar($item, $branch);
            $this->info("Rama {$branch} ya existía; checkout hecho. Registrada en el item #{$item->id}.");
            return self::SUCCESS;
        }

        if (trim($this->git(['status', '--porcelain'])->getOutput()) !== '') {
            $this->error('El árbol de trabajo tiene cambios sin commitear; commitea o descarta antes de ramificar.');
            return self::FAILURE;
        }

        // Rama desde main.
        $this->git(['checkout', 'main']);
        if (! $this->git(['checkout', '-b', $branch])->isSuccessful()) {
            $this->error("No se pudo crear la rama {$branch}.");
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
