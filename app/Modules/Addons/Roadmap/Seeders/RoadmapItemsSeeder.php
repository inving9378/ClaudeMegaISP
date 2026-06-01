<?php

namespace App\Modules\Addons\Roadmap\Seeders;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

class RoadmapItemsSeeder
{
    public function run(): void
    {
        $sidebarPrompt = <<<'PROMPT'
# Verificar y finalizar el rediseño del sidebar

## Estado
El sidebar fue reescrito. Verificar visualmente que:

1. Orden exacto (15 items): Dashboard → Planes → Clientes potenciales → Clientes →
   Gestión de red → Finanzas → Inventario → OLTs → Mapas → Cobranza → MegaFamilia →
   Embajadores → War Room → Administración → Configuración

2. Marketing aparece DENTRO de Finanzas como sub-sección con separador "Marketing"
   (no como item de primer nivel).

3. OLTs es item standalone (posición 8), separado de Gestión de red.

4. Gestión de red solo tiene: Enrutadores + Redes IPv4 (sin OLTs ni ONUs).

5. Desarrollador es accordion con DevTools adentro. Solo visible para rol DESARROLLADOR.
   Acento naranja. Ubicado al final del sidebar.

6. Dark mode: el separador "Marketing" y el acento naranja del Desarrollador se ven bien.

## Archivos clave
- app/Modules/Core/Layout/views/sidebar.blade.php (reescritura completa)
- app/Modules/Addons/GestionRed/module.json (menu.children: Routers, Red IPv4 solo)
- app/Modules/Addons/Marketing/module.json (sidebar.location=submenu, parent=finanzas)
- app/Modules/Addons/WarRoom/module.json (sidebar.location=primary, order=13)
- app/Modules/Addons/DevTools/module.json (sidebar.location=developer, order=1)
- app/Modules/Core/ModuleManager/Services/ModuleRegistry.php (getSubmenuItemsFor)
- app/Modules/Core/Layout/ViewComposers/SidebarComposer.php

## Si hay ajustes visuales pendientes
Describe el problema y pide el fix de CSS específico.
PROMPT;

        $items = [
            [
                'title'          => 'Rediseño del sidebar (lista curada + accordion)',
                'description'    => 'Sidebar reescrito con lista curada de 15 items, Marketing dentro de Finanzas, OLTs standalone, Desarrollador como accordion con DevTools. Verificar visualmente y aplicar ajustes si faltan.',
                'status'         => 'in_progress',
                'priority'       => 'alta',
                'target_version' => 'v0.9',
                'prompt'         => $sidebarPrompt,
                'position'       => 1,
                'started_at'     => now(),
            ],
            [
                'title'          => 'Política keep_data en uninstall (verificar fix aplicado)',
                'description'    => 'Con keep_data:true el uninstall no debe tocar permisos ni asignaciones. Confirmar en pantalla tras reporte de Claude Code.',
                'status'         => 'pending',
                'priority'       => 'alta',
                'target_version' => 'v0.9',
                'position'       => 1,
            ],
            [
                'title'          => 'Piloto de migración del sistema original',
                'description'    => 'Dump del sistema original → entorno limpio del nuevo → php artisan migrate → instalar módulos → validar datos legacy desde la UI nueva.',
                'status'         => 'pending',
                'priority'       => 'alta',
                'target_version' => 'v0.9',
                'position'       => 2,
            ],
            [
                'title'          => 'Cerrar módulo Mapas contra checklist',
                'description'    => 'Aplicar el checklist de cierre de Fase 2 al módulo Mapas, igual que se hizo con Vendedores.',
                'status'         => 'pending',
                'priority'       => 'baja',
                'target_version' => 'v0.9',
                'position'       => 3,
            ],
            [
                'title'          => 'Infra de ficha de cliente extensible',
                'description'    => 'Habilitar pestañas client_tab declaradas como deferred:true. Desbloquea MegaFamilia.',
                'status'         => 'pending',
                'priority'       => 'media',
                'target_version' => 'v1.0',
                'position'       => 4,
            ],
            [
                'title'          => 'Motor de servicios contratables',
                'description'    => 'Catálogo de planes que reciba service_type de los addons, con precio, promociones y paquetes. Desbloquea MegaFamilia y Embajadores.',
                'status'         => 'pending',
                'priority'       => 'media',
                'target_version' => 'v1.0',
                'position'       => 5,
            ],
            [
                'title'          => 'Catálogo de API con scopes por llave',
                'description'    => 'Pantalla en Configuración → API que liste endpoints publicados por módulos y genere llaves con scope.',
                'status'         => 'pending',
                'priority'       => 'baja',
                'target_version' => 'v1.0',
                'position'       => 6,
            ],
            [
                'title'          => 'Pantallas de documentación contextual y manual general',
                'description'    => 'Panel lateral de ayuda por pantalla (estilo Splynx) y manual navegable con screens de cada módulo.',
                'status'         => 'pending',
                'priority'       => 'baja',
                'target_version' => 'v1.0',
                'position'       => 7,
            ],
            [
                'title'          => 'Conectar chat IA al registry (knowledge/actions)',
                'description'    => 'Conectar IaChatFloat.vue al ModuleRegistry para que consuma ai.knowledge y ai.actions declarados por cada módulo.',
                'status'         => 'pending',
                'priority'       => 'baja',
                'target_version' => 'v1.0',
                'position'       => 8,
            ],
            [
                'title'          => 'Limpiar 14 registros huérfanos en tabla modules',
                'description'    => '14 registros huérfanos de clases eliminadas en refactores antiguos. No bloqueante.',
                'status'         => 'pending',
                'priority'       => 'baja',
                'target_version' => 'v1.0',
                'position'       => 9,
            ],
            [
                'title'          => 'Kit base del sistema modular',
                'description'    => 'ModuleDefinition, ModuleRegistry, ModuleLifecycleService con install/upgrade/uninstall, paneles dinámicos de Administración y Configuración.',
                'status'         => 'done',
                'target_version' => 'v0.7',
                'completed_at'   => now(),
                'position'       => 1,
            ],
            [
                'title'          => 'Migración del módulo Configuración',
                'description'    => 'Panel ModuleConfigPanel.vue agrupando por type. Switch hecho en /configuracion.',
                'status'         => 'done',
                'target_version' => 'v0.8',
                'completed_at'   => now(),
                'position'       => 2,
            ],
            [
                'title'          => 'Cierre del módulo Vendedores contra checklist',
                'description'    => 'Checklist de 17 ítems completado. Convención client_tab.deferred implementada como sistema.',
                'status'         => 'done',
                'target_version' => 'v0.8',
                'completed_at'   => now(),
                'position'       => 3,
            ],
            [
                'title'          => 'Sección "Addons" separada en sidebar',
                'description'    => 'Se decidió mezclar los addons con el núcleo en su grupo correspondiente y luego restringirlos por la lista curada de 15.',
                'status'         => 'cancelled',
                'position'       => 1,
            ],
        ];

        foreach ($items as $item) {
            RoadmapItem::create($item);
        }
    }
}
