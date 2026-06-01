<?php

namespace App\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Contracts\ModuleDefinition as BaseDefinition;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    public function install(): void
    {
        // Sembrar items iniciales solo si la tabla está vacía
        if (RoadmapItem::count() === 0) {
            (new Seeders\RoadmapItemsSeeder())->run();
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void {}

    public function uninstall(bool $keepData = false): void {}
}
