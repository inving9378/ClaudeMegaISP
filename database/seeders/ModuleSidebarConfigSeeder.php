<?php

namespace Database\Seeders;

use App\Models\ModuleSidebarConfig;
use Illuminate\Database\Seeder;

class ModuleSidebarConfigSeeder extends Seeder
{
    public function run(): void
    {
        // ─── MÓDULOS CORE (is_core=true, show_in_sidebar=true, no se pueden ocultar) ──

        // 1. Dashboard
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'dashboard'], [
            'sidebar_label'    => 'Dashboard',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 1,
            'sidebar_icon'     => 'home',
            'config_moved'     => false,
        ]);

        // 2. Planes (addon-planes, core porque es funcionalidad base ISP)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'planes'], [
            'sidebar_label'    => 'Planes',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 2,
            'sidebar_icon'     => 'fa-grid',
            'config_moved'     => false,
        ]);

        // 3. CRM — Clientes potenciales
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'crm'], [
            'sidebar_label'    => 'Clientes potenciales',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 3,
            'sidebar_icon'     => 'user-x',
            'config_moved'     => false,
        ]);

        // 4. Clientes
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'clientes'], [
            'sidebar_label'    => 'Clientes',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 4,
            'sidebar_icon'     => 'user-check',
            'config_moved'     => false,
        ]);

        // 5. Gestión de red (addon-gestion-red)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'gestion-red'], [
            'sidebar_label'    => 'Gestión de red',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 5,
            'sidebar_icon'     => 'box',
            'config_moved'     => false,
        ]);

        // 6. Finanzas (addon-finanzas)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'finanzas'], [
            'sidebar_label'    => 'Finanzas',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 6,
            'sidebar_icon'     => 'fa-grid',
            'config_moved'     => false,
        ]);

        // 7. Inventario (addon-inventario — core para ISP)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'inventario'], [
            'sidebar_label'    => 'Inventario',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 7,
            'sidebar_icon'     => 'archive',
            'config_moved'     => false,
        ]);

        // 8. OLTs (standalone, core ISP)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'olts'], [
            'sidebar_label'    => 'OLTs',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 8,
            'sidebar_icon'     => 'server',
            'config_moved'     => false,
        ]);

        // 9. Mapas (addon-mapas)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'mapas'], [
            'sidebar_label'    => 'Mapas',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 9,
            'sidebar_icon'     => 'map',
            'config_moved'     => false,
        ]);

        // 10. Administración (link fijo, core)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'administracion'], [
            'sidebar_label'    => 'Administración',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 96,
            'sidebar_icon'     => 'command',
            'config_moved'     => false,
        ]);

        // 11. Configuración (link fijo, core)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'configuracion'], [
            'sidebar_label'    => 'Configuración',
            'is_core'          => true,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 97,
            'sidebar_icon'     => 'tool',
            'config_moved'     => false,
        ]);

        // ─── ADDONS VISIBLES EN SIDEBAR (is_core=false, show_in_sidebar=true) ──────

        // Cobranza Blaster (addon-cobranza-blaster) — pos 10, sección menu
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'cobranza-blaster'], [
            'sidebar_label'    => 'Cobranza',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 10,
            'sidebar_icon'     => 'phone-call',
            'config_moved'     => false,
        ]);

        // MegaFamilia (addon-megafamilia) — pos 11
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'megafamilia'], [
            'sidebar_label'    => 'MegaFamilia',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 11,
            'sidebar_icon'     => 'shield',
            'config_moved'     => false,
        ]);

        // Scheduling (addon-scheduling) — pos 12
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'scheduling'], [
            'sidebar_label'    => 'Scheduling',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 12,
            'sidebar_icon'     => 'check-square',
            'config_moved'     => false,
        ]);

        // Embajadores (addon-embajadores) — pos 13
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'embajadores'], [
            'sidebar_label'    => 'Embajadores',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 13,
            'sidebar_icon'     => 'award',
            'config_moved'     => false,
        ]);

        // Flotas (addon-flotas) — pos 14
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'flotas'], [
            'sidebar_label'    => 'Flotas',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 14,
            'sidebar_icon'     => 'truck',
            'config_moved'     => false,
        ]);

        // Talento (addon-talento) — pos 15
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'talento'], [
            'sidebar_label'    => 'Talento',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 15,
            'sidebar_icon'     => 'users',
            'config_moved'     => false,
        ]);

        // Marketing (addon-marketing) — sub_item de Finanzas (sidebar.blade inline)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'marketing'], [
            'sidebar_label'    => 'Marketing',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'sub_item',
            'sidebar_parent'   => 'finanzas',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 60,
            'sidebar_icon'     => 'fa-bullhorn',
            'config_moved'     => false,
        ]);

        // Desarrollador (addon-devtools) — rol-gated, pos 98
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'devtools'], [
            'sidebar_label'    => 'Desarrollador',
            'is_core'          => false,
            'show_in_sidebar'  => true,
            'sidebar_location' => 'direct',
            'sidebar_section'  => 'menu',
            'sidebar_position' => 98,
            'sidebar_icon'     => 'code',
            'config_moved'     => false,
        ]);

        // ─── ADDONS INSTALADOS NO VISIBLES EN SIDEBAR (show_in_sidebar=false) ──────

        // War Room — solo accesible desde panel Administración
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'warroom'], [
            'sidebar_label'              => 'War Room',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'menu',
            'sidebar_position'           => 50,
            'sidebar_icon'               => 'fa-tower-broadcast',
            'admin_section'              => 'operaciones',
            'configuracion_subsection'   => 'War Room',
            'config_moved'               => false,
        ]);

        // Demo — addon de pruebas, oculto en producción
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'demo'], [
            'sidebar_label'              => 'Demo',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-flask',
            'admin_section'              => 'desarrollo',
            'configuracion_subsection'   => 'Demo',
            'config_moved'               => false,
        ]);

        // Evaluador Empresarial (suppressed — solo en /configuracion)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'evaluador-empresarial'], [
            'sidebar_label'              => 'Evaluador Empresarial',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-chart-line',
            'admin_section'              => 'analitica',
            'configuracion_subsection'   => 'Evaluador Empresarial',
            'config_moved'               => false,
        ]);

        // Hub — Integration Hub, solo en /integraciones
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'hub'], [
            'sidebar_label'              => 'Integraciones',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-plug',
            'admin_section'              => 'integraciones',
            'configuracion_subsection'   => 'Hub de Integraciones',
            'config_moved'               => false,
        ]);

        // Manual — documentación interna, solo en /configuracion
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'manual'], [
            'sidebar_label'              => 'Manual',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-book',
            'admin_section'              => 'ayuda',
            'configuracion_subsection'   => 'Manual del sistema',
            'config_moved'               => false,
        ]);

        // Smart Import/Export (suppressed)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'smart-import-export'], [
            'sidebar_label'              => 'Import / Export',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-file-import',
            'admin_section'              => 'herramientas',
            'configuracion_subsection'   => 'Import/Export',
            'config_moved'               => false,
        ]);

        // WhatsApp Agent (suppressed)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'whatsapp-agent'], [
            'sidebar_label'              => 'WhatsApp',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-whatsapp',
            'admin_section'              => 'comunicacion',
            'configuracion_subsection'   => 'WhatsApp Agent',
            'config_moved'               => false,
        ]);

        // IA (addon-ia — chat flotante, no item de sidebar)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'ia'], [
            'sidebar_label'              => 'Asistente IA',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-robot',
            'admin_section'              => 'ia',
            'configuracion_subsection'   => 'Asistente IA',
            'config_moved'               => false,
        ]);

        // Mensajes
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'mensajes'], [
            'sidebar_label'              => 'Mensajes',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-envelope',
            'admin_section'              => 'comunicacion',
            'configuracion_subsection'   => 'Mensajes',
            'config_moved'               => false,
        ]);

        // Payments (addon-payments)
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'payments'], [
            'sidebar_label'              => 'Pagos Online',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-credit-card',
            'admin_section'              => 'finanzas',
            'configuracion_subsection'   => 'Pagos Online',
            'config_moved'               => false,
        ]);

        // Tickets
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'tickets'], [
            'sidebar_label'              => 'Tickets',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-ticket-alt',
            'admin_section'              => 'soporte',
            'configuracion_subsection'   => 'Tickets',
            'config_moved'               => false,
        ]);

        // Vendedores
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'vendedores'], [
            'sidebar_label'              => 'Vendedores',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-user-tie',
            'admin_section'              => 'ventas',
            'configuracion_subsection'   => 'Vendedores',
            'config_moved'               => false,
        ]);

        // Reportes
        ModuleSidebarConfig::updateOrCreate(['module_key' => 'reportes'], [
            'sidebar_label'              => 'Reportes',
            'is_core'                    => false,
            'show_in_sidebar'            => false,
            'sidebar_location'           => 'direct',
            'sidebar_section'            => 'modulos',
            'sidebar_position'           => 99,
            'sidebar_icon'               => 'fa-chart-bar',
            'admin_section'              => 'analitica',
            'configuracion_subsection'   => 'Reportes',
            'config_moved'               => false,
        ]);
    }
}
