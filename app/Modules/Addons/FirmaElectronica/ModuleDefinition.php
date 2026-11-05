<?php

namespace App\Modules\Addons\FirmaElectronica;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    public function install(): void {}

    public function upgrade(string $fromVersion, string $toVersion): void {}

    /** `keep_data: true`: la evidencia de firma no se borra al desinstalar. */
    public function uninstall(bool $keepData = false): void {}
}
