<?php

namespace App\Modules\Addons\VozMayorista;

use App\Modules\BaseModuleServiceProvider;
use App\Support\RolInstancia;

/**
 * Voz Mayorista — plano de control comercial del servicio de telefonía que
 * Meganet revende a sus clientes.
 *
 * MÓDULO DE OPERADOR. El código viaja a todas las instalaciones de MegaISP,
 * pero este módulo solo debe funcionar en la de Meganet: administra tarifas de
 * carrier, márgenes, inventario de DID y capacidad contratada. En la
 * instalación de un cliente arrendado vive el módulo VoIP/PBX, que es otra
 * cosa distinta (ver README.md del módulo).
 *
 * El blindaje son TRES barreras independientes, y esta clase es la tercera:
 *   1. Ciclo de vida — `ModuleLifecycleService::install()` rechaza activarlo si
 *      INSTANCE_ROLE no es 'operador', y no deja fila en module_registry.
 *   2. Rutas — el middleware `rol.instancia:operador` las cierra con 403.
 *   3. Boot (aquí) — si la instalación no es de operador, el provider no carga
 *      nada: ni rutas, ni vistas, ni migraciones. Y como el menú del sidebar se
 *      arma desde los módulos activos, tampoco aparece la entrada.
 *
 * Son tres y no una porque cada una cubre el hueco de la anterior: si alguien
 * marca `active = 1` a mano en la base, saltándose el ciclo de vida, las otras
 * dos siguen cerradas.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-voz-mayorista';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'voz-mayorista';

    public function boot(): void
    {
        // Tercera barrera. Va ANTES del parent::boot() a propósito: si esta
        // instalación no es de operador, no se carga ni una ruta del módulo.
        if (! RolInstancia::esOperador()) {
            return;
        }

        parent::boot();
    }
}
