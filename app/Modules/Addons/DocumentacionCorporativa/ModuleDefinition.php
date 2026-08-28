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
     * `keep_data: true` en module.json es PROTECCIÓN REAL, no sólo intención:
     * `ModuleLifecycleService::resolveKeepData()` fuerza el modo conservador
     * aunque quien desinstale pida lo contrario. (Al escribir la Fase 0 esa clave
     * era inerte; se reportó como hallazgo —item #669— y se arregló ahí antes de
     * cerrar esta fase.)
     *
     * Consecuencia: desinstalar el módulo desactiva el registry pero NO tira las
     * tablas `dc_*` ni retira los permisos. Un expediente corporativo no se borra
     * por desinstalar la pantalla que lo muestra.
     */
    public function uninstall(bool $keepData = false): void {}
}
