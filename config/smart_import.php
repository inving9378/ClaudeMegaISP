<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smart Import Dynamic Resolution
    |--------------------------------------------------------------------------
    |
    | The importer should not depend on a giant hardcoded table map. It first
    | reads the real DB/dump schema, discovers Eloquent models by getTable(),
    | and only then applies these small overrides for exceptional tables.
    |
    */

    'model_discovery' => [
        'paths' => [
            app_path('Models'),
            app_path('Modules'),
        ],
    ],

    'strict_tables' => [
        'balances',
        'billing_configurations',
        'clients',
        'client_bundle_services',
        'client_custom_services',
        'client_internet_services',
        'client_invoices',
        'client_main_information',
        'client_users',
        'client_voz_services',
        'company_information',
        'crm_main_information',
        'cut_extras_incomes',
        'cut_installations',
        'cut_suppliers_expenses',
        'cuts_observations',
        'discounts',
        'distribution_commission_sales',
        'documentation_contents',
        'documentation_menus',
        'documentation_submenus',
        'evaluaciones_empresariales',
        'field_modules',
        'files',
        'ia_notas_proyecto',
        'ia_tareas',
        'inventory_items',
        'inventory_item_media',
        'inventory_item_types',
        'inventory_movements',
        'map_devices_ports_connections',
        'map_layers',
        'map_routes',
        'mikrotiks',
        'mikrotik_configs',
        'module_registry',
        'networks',
        'payments',
        'payment_by_rule',
        'sellers',
        'tasks',
        'whatsapp_instances',
    ],

    'overrides' => [
        'permissions' => [
            'module' => 'Administracion',
            'mode' => 'raw',
            'conflict_keys' => ['name', 'guard_name'],
            'identity_priority' => 'override',
        ],

        'roles' => [
            'module' => 'Administracion',
            'mode' => 'raw',
            'conflict_keys' => ['name', 'guard_name'],
            'identity_priority' => 'override',
        ],

        'model_has_permissions' => [
            'module' => 'Administracion',
            'mode' => 'raw',
        ],

        'model_has_roles' => [
            'module' => 'Administracion',
            'mode' => 'raw',
        ],

        'role_has_permissions' => [
            'module' => 'Administracion',
            'mode' => 'raw',
        ],

        'jobs' => [
            'module' => 'Sistema',
            'mode' => 'raw',
        ],

        'failed_jobs' => [
            'module' => 'Sistema',
            'mode' => 'raw',
        ],

        'migrations' => [
            'module' => 'Sistema',
            'mode' => 'raw',
            'conflict_keys' => ['migration'],
            'identity_priority' => 'override',
        ],

        'system_settings' => [
            'module' => 'Sistema',
            'mode' => 'raw',
            'conflict_keys' => ['key'],
            'identity_priority' => 'override',
        ],

        'setting_table' => [
            'module' => 'Sistema',
            'mode' => 'raw',
        ],

        'crud_packages' => [
            'module' => 'Sistema',
            'mode' => 'raw',
        ],

        'plan_type_billings' => [
            'module' => 'Finanzas',
            'mode' => 'raw',
        ],

        'ia_chat_conversations' => [
            'module' => 'IA',
            'mode' => 'raw',
        ],
    ],
];
