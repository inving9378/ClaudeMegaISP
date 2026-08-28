<?php

namespace App\Modules\Addons\DocumentacionCorporativa;

use App\Modules\Contracts\ModuleDefinition as BaseDefinition;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    public function install(): void {}

    public function upgrade(string $fromVersion, string $toVersion): void {}

    /**
     * `keep_data` se declara en module.json para dejar por escrito la intención
     * (el expediente corporativo NO se tira al desinstalar), pero hoy esa clave
     * del manifiesto es INERTE: `ModuleLifecycleService` sólo honra el parámetro
     * `$keepData` de la llamada. Hallazgo registrado en la Hoja de Ruta.
     */
    public function uninstall(bool $keepData = false): void {}
}
