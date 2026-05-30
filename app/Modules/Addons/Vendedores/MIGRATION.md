# MIGRATION.md — Módulo Vendedores

## Estado de migraciones

Este módulo es **legacy**: sus tablas se crearon antes de que existiera el sistema
de módulos de Medussa. Las migraciones viven en la carpeta global
`database/migrations/` y **no deben moverse** porque contienen datos de producción
y son referenciadas por el historial de `migrate:status`.

## Tablas del módulo (migraciones globales)

| Tabla | Migración de origen |
|-------|---------------------|
| `sellers` | `2025_02_17_144910_add_module_vendor.php` |
| `seller_status` | *(incluida en add_module_vendor)* |
| `seller_types` | *(incluida en add_module_vendor)* |
| `commissions_rules` | `2025_02_18_143057_add_module_comission_rules.php` |
| `commissions_rules_sellers` | `2025_02_18_143057_add_module_comission_rules.php` |
| `history_sellers_rules` | `2025_03_12_090222_create_history_sellers_rules.php` |
| `commissions` | `2025_02_18_143057_add_module_comission_rules.php` |
| `commissions_details` | `2025_03_17_130436_payment_by_rule.php` |
| `cut_boxs` | `2025_11_24_135133_create_cut_boxs_table.php` |
| `cut_extras_incomes` | `2025_11_18_221733_create_cuts_extras_incomes_table.php` |
| `cut_installations` | *(incluida en create_cut_boxs_table)* |
| `cut_suppliers_expenses` | `2025_11_19_154848_create_cuts_suppliers_expenses_table.php` |
| `cuts_observations` | `2025_11_20_140803_create_cuts_observations_table.php` |
| `prospects` | `2025_02_17_144910_add_module_vendor.php` |
| `prospect_followups` | *(incluida en add_module_vendor)* |
| `discounts` | `2025_04_07_091622_create_discounts_table.php` |
| `payment_sellers` | `2025_03_17_130436_payment_by_rule.php` |
| `transaction_sellers` | `2025_05_13_124126_add_module_movement_seller.php` |

## Política de uninstall

El comando `php artisan module:lifecycle uninstall addon-vendedores` retira permisos,
menú, config_sections, api_endpoints e IA del sistema de módulos, pero **no dropea**
ninguna tabla. Esto es intencional: las tablas contienen datos de producción y su
ciclo de vida está ligado al sistema global, no al módulo.

Para un borrado completo de datos se requiere intervención manual en base de datos.

## Migraciones delta del módulo

Las migraciones nuevas específicas de este módulo (a partir de la versión 1.0.0)
van en `migrations/` de este directorio y sí tienen `down()` limpio.

## Endpoints fuera del catálogo api_endpoints

Los siguientes grupos de rutas existen en `routes.php` pero se omitieron del
catálogo porque son rutas de render de UI (devuelven vistas Blade), no datos:

- `GET /vendedores/{id}/seguimiento-vendedor/{seller_id}` — vista de seguimiento
- `GET /vendedores/seguimiento-me/` — panel propio del vendedor autenticado
- `GET /sellers/cuts/box-pdf/{id}` — descarga PDF (streaming, no API)
- `GET /vendedores/{id}/pdf` — credencial PDF
- Todos los `edit` y `showView` de controladores que devuelven `view()`

Se incluyeron en el catálogo solo los endpoints que devuelven JSON o realizan
mutaciones de datos, con su `scope` de permiso correspondiente.
