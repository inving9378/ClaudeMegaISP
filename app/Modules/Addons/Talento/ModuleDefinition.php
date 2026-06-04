<?php

namespace App\Modules\Addons\Talento;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    public function install(): void {}

    public function upgrade(string $fromVersion, string $toVersion): void {}

    public function uninstall(bool $keepData = false): void {}
}
