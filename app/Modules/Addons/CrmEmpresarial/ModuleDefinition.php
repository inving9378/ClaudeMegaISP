<?php

namespace App\Modules\Addons\CrmEmpresarial;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    public function install(): void {}

    public function upgrade(string $fromVersion, string $toVersion): void {}

    /** `keep_data: true`: desinstalar no tira las tablas `crm_expediente*` ni los permisos. */
    public function uninstall(bool $keepData = false): void {}
}
