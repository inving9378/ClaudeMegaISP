# Estrategia de datos en dev — Fase 2: catálogos vacíos que rompen formularios hoy

Item #9990480 (sub-item de #9990478, depende de la Fase 1 #9990479). SOLO LECTURA — no se
importó, sembró ni borró nada. Este documento solo entrega veredicto + recomendación.

## Alcance

Las 13 tablas del bucket **CATÁLOGO** medidas en la Fase 1
(`docs/estrategia-datos-dev-item-9990478-fase1-inventario.md`, rama
`circuito/item-9990479-fase-1-inventario-completo-de-las-223`, aún sin mergear a `main` al momento
de escribir esto): `active_equipment_types`, `box_types`, `brands`, `colors`,
`contratable_packages`, `contratable_services`, `ipv6_policies`, `passive_equipment_types`,
`positions`, `project_types`, `social_providers`, `trenche_types`, `tube_types`.

Metodología: para cada tabla se investigó en código (controllers/repositories/Vue o Blade que la
consumen vía `<select>`/dropdown) si un módulo **ACTIVO** depende de ella para que su formulario
funcione hoy — con cita de archivo:línea — y si el campo es requerido o tiene fallback.

## Resumen

| Tabla | Veredicto | Módulo | Motivo corto |
|---|---|---|---|
| `brands` | 🔴 **ROMPE FORMULARIO HOY** | Mapas (activo) | Raíz de la cascada: `<select required>` sin opciones en 5 catálogos hijos |
| `box_types` | 🔴 **ROMPE FORMULARIO HOY** | Mapas (activo) | `<select required>` vacío bloquea alta de Caja/NAP (constraint HTML5) |
| `trenche_types` | 🔴 **ROMPE FORMULARIO HOY** | Mapas (activo) | Sin `required` HTML, pero FK NOT NULL revienta el `INSERT` al crear una zanja |
| `active_equipment_types` | 🔴 **ROMPE FORMULARIO HOY** | Mapas (activo) | Sin `required` HTML, pero el controller usa `$type->ethernet_ports` sin guard → excepción al dar de alta equipo activo |
| `passive_equipment_types` | 🔴 **ROMPE FORMULARIO HOY** | Mapas (activo) | Mismo patrón que equipo activo (`$type->ports` sin guard) |
| `colors` | 🟡 **ROMPE FORMULARIO, pero ruta dormant** | Mapas (activo, pero flujo sin adopción) | `<select required>` vacío en "Trazar ruta de fibra" (`map_route`/`map_link`) — ese flujo tiene 0 filas históricas, nadie lo usa hoy |
| `contratable_packages` / `contratable_services` | 🟢 NO ROMPE NADA HOY | Planes (activo) | Lista con empty-state amigable, no `<select>` requerido; el flujo real de venta de planes usa `internets`/`voises`/`customs`/`bundles`, tablas separadas y pobladas |
| `ipv6_policies` | 🟢 NO ROMPE NADA HOY | Ipv6 (sin `module.json`/controllers — en construcción) | El modelo `Ipv6Policy` no tiene ningún consumidor; la pantalla IPv6 real (`Ipv6ConfigController`) ni la referencia |
| `positions` | 🟢 NO ROMPE NADA HOY | Mapas (activo) | No es catálogo pre-poblado: se autocrea (`morphOne`) al dar de alta cualquier entidad geo-referenciada — 0 filas es el estado esperado |
| `project_types` | 🟢 NO ROMPE NADA HOY | — (sin módulo "Proyectos" de negocio real) | Único uso es el listado genérico de `SmartImportService`; ningún controller/Vue/Blade real la consume |
| `social_providers` | 🟢 NO ROMPE NADA HOY | — (sin modelo Eloquent) | Vestigio huérfano; único hit es un string en `translation_columns_table.php` |

**4 catálogos rompen hoy de forma directa** (`brands`, `box_types`, `trenche_types`, más los 2 de
equipo activo/pasivo que comparten el mismo patrón de crash) + **1 caso limítrofe** (`colors`,
rompe en código pero la ruta que lo usa está dormant/sin adopción real).

## Detalle por tabla

### 🔴 `brands` — ROMPE FORMULARIO HOY (raíz de la cascada)

- Modelo: `app/Models/Brand.php` (dominio: marca de equipo de infraestructura FTTH — Mapas, no
  Flotas/vehículos).
- Consumido por `belongsTo(Brand::class, 'brand_id')` en `BoxType`, `ActiveEquipmentType`,
  `PassiveEquipmentType`, `TrencheTypes` — los 4 con `brand_id` **NOT NULL** en la BD.
- Formularios de alta de cada uno de esos 4 catálogos (`resources/views/meganet/module/mapas/types/
  box_type.blade.php:31`, `active_equipment_type.blade.php:20`, `passive_equipment_type.blade.php:20`,
  `trench_type.blade.php:38`, y también `racket_type.blade.php:20`) tienen
  `<select class="brand_select" name="brand_id" required>` alimentado vía AJAX contra
  `maps.brand.list` → `BrandController::getListToSelect` (`app/Modules/Addons/Mapas/routes.php:138`)
  → consulta `brands` (0 filas) → `<select>` sin opciones.
- Módulo Mapas activo (`app/Modules/Addons/Mapas/module.json:10` → `"active": true`).
- **Efecto**: hoy nadie puede completar NINGUNO de los 5 catálogos hijos desde la UI de
  administración (todos exigen elegir marca), y por cascada tampoco "Agregar caja NAP" desde el
  mapa (`objectForms/box.blade.php:4`, que exige `box_type_id`).

### 🔴 `box_types` — ROMPE FORMULARIO HOY

- Modelo: `app/Models/BoxType.php`. Consumidor: `resources/views/meganet/module/mapas/
  objectForms/box.blade.php:4-5` — `<select name="box_type_id" required>` sin opciones cuando
  `$boxTypes` (cargado en `MapasController.php:121-123` vía `BoxTypeRepository->getAll()`) está
  vacío.
- Es el formulario para **crear una Caja/NAP nueva** desde el mapa
  (`MapasController::objectCreate`, líneas 131-170). Constraint HTML5 bloquea el submit.
- Mismo patrón en edición (`data/box.blade.php:30`).

### 🔴 `trenche_types` — ROMPE FORMULARIO HOY (bloqueo indirecto)

- Modelo: `App\Models\TrencheTypes`. Consumidor: `resources/views/meganet/module/mapas/
  objectForms/trenche.blade.php:10-14` — `<select name="trenche_type_id">` **sin `required`** y
  **sin `<option>` vacío**: si `$types` (cargado en `MapasController.php:119-120` vía
  `TrencheTypesRepository->getAll()`) está vacío, el `<select>` queda sin ninguna opción y se
  envía `""`.
- `TrenchRepository::getForDatatable()` hace `INNER JOIN` obligatorio con `trenche_types` y
  `brands` — el `INSERT` de una zanja con `trenche_type_id` inválido revienta contra el FK,
  devolviendo el error genérico 490 de `SimpleService`. En la práctica no se puede crear/gestionar
  una zanja utilizable.

### 🔴 `active_equipment_types` / `passive_equipment_types` — ROMPEN FORMULARIO HOY (crash de backend)

- Consumidores: `resources/views/meganet/module/mapas/data/rack.blade.php:148` (activo) y `:83`
  (pasivo) — `<select name="type_id">` **sin `required`**, poblado desde `MapService.php:100-101`.
- El campo no es HTML-requerido, pero `ActiveEquipmentController::store`
  (`app/Modules/Addons/Mapas/Controllers/Mapas/ActiveEquipmentController.php:43-55`) y
  `PassiveEquipmentController::store` (líneas equivalentes) acceden a `$type->ethernet_ports` /
  `$type->ports` **sin verificar que el tipo exista** — con la tabla vacía no hay `type_id` válido
  que enviar, la relación resuelve `null`, y el acceso a la propiedad truena (capturado por el
  `catch` genérico de `SimpleService`, mensaje "Ha ocurrido un error"). No se puede dar de alta
  equipo activo/pasivo.

### 🟡 `colors` — ROMPE FORMULARIO, pero es una ruta sin adopción real (caso limítrofe)

- Modelo: `app/Models/Color.php`. Consumido por `belongsTo(Color::class)` en `Buffer` y `Fiber`
  (`color_id` NOT NULL en ambas).
- `<select id="color_id" name="color_id" required>` en
  `resources/views/meganet/module/mapas/auxViews/map_route.blade.php:49` y
  `data/map_link.blade.php:56`, poblado con `Color::all()` desde `MapRouteController.php:60` /
  `MaplinkController.php:124` — 0 opciones porque `colors` está vacía.
- **Matiz que lo saca de la categoría roja pura**: no existe form de creación de Buffer/Fiber en
  la UI (sus controllers solo exponen `list`); el único form afectado es el legacy "Trazar ruta de
  fibra" (`map_route`/`map_link`), que pertenece al sistema de inventario físico formal **dormant**
  (0 filas en `map_routes`/`map_links` — confirmado en `docs/mapas-inventario-item-1000000.md`),
  distinto del sistema vivo de dibujo (`map_layers`, 6,689 filas) que **no** usa `colors`.
- **Veredicto**: el código rompería si alguien abriera esa pantalla específica hoy, pero nadie la
  usa en el flujo real vigente. Se documenta aparte para no inflar la lista de bloqueos activos.

### 🟢 `contratable_packages` / `contratable_services` — NO ROMPE NADA HOY

- Módulo `addon-planes` activo (`app/Modules/Addons/Planes/module.json:8`), pero **no** es el
  flujo general de "vender plan" al cliente (ese usa `internets`/`voises`/`customs`/`bundles`,
  tablas separadas y ya pobladas). `ClientCrud.vue` del CRM no referencia `Contratable`.
- Alimenta solo la pestaña "Servicios contratados" de Flotas/MegaFamilia
  (`ContratablesClientTab.vue:14-18`) — con catálogo vacío renderiza un empty-state explícito
  ("No hay servicios contratables activos en el catálogo"), sin `<select>` requerido ni crash.

### 🟢 `ipv6_policies` — NO ROMPE NADA HOY (módulo en construcción)

- `app/Modules/Addons/Ipv6/` no tiene `module.json` ni `Controllers/` — solo modelos. Cero
  controllers/rutas/consumidores reales de `Ipv6Policy`.
- La pantalla IPv6 real y funcional (`App\Http\Controllers\Network\Ipv6ConfigController`, montada
  standalone en `routes/web.php:245-263`) usa `ClientInternetService`/`Router`, **no**
  `Ipv6Policy` — y su propio comentario en código dice explícitamente que no depende de la tabla.
  El Vue (`Ipv6Config.vue:12-13`) advierte "Nada de esto se guarda todavía (persistencia
  pendiente)". Confirma el bucketing de Fase 1 (`ipv6_*` = en construcción).

### 🟢 `positions` — NO ROMPE NADA HOY

- No es catálogo pre-poblado: `morphOne(Position::class, 'positionable')` en `CutFiber`, `Pole`,
  `Point`, `Trench`, `Site`, `Box`. Se crea en el mismo request que da de alta la entidad geo
  (`MapasController.php:156`, a partir de lat/lng del clic en el mapa). 0 filas es el estado
  esperado mientras esas entidades también estén vacías (coherente con el resto del inventario de
  Mapas).

### 🟢 `project_types` — NO ROMPE NADA HOY

- Único uso real en `app/`: entrada genérica en `SmartImportService.php:690`. No existe módulo
  "Proyectos" de negocio con un form de alta que use `<select>` de tipo — `CentroProyecto`
  (`app/Modules/Addons/CentroProyecto/`, activo) es un panel de observabilidad y no referencia
  `ProjectType` en absoluto. Cero uso en `resources/js` o `resources/views`.

### 🟢 `social_providers` — NO ROMPE NADA HOY

- Sin modelo Eloquent, sin controller, sin rutas. Único hit en código vivo:
  `resources/lang/es/translation_columns_table.php:1427-1433` (mapeo de nombres de columna para
  traducción de UI genérica). Ni siquiera aparece en `SmartImportService.php`. Vestigio de un
  login social nunca implementado o descontinuado — solo existe en migraciones legacy/dumps SQL
  históricos (`2020_08_25...create_social_providers_table`, ausente de `database/migrations/`
  actual).

## Recomendación (solo veredicto — no se ejecuta aquí)

Las 6 tablas que rompen algo (`brands`, `box_types`, `trenche_types`, `active_equipment_types`,
`passive_equipment_types`, y `colors` en su ruta dormant) ya están **registradas en
`SmartImportService.php`** con modelo y `conflict_keys` definidos (`positions`, `box_types`,
`colors`, `brands`, `project_types`, `active_equipment_types`, `passive_equipment_types`,
`trenche_types`, `tube_types` — 9 de las 13 aparecen en el importador; las 4 restantes
—`contratable_packages`, `contratable_services`, `ipv6_policies`, `social_providers`— no, pero
tampoco lo necesitan porque no rompen nada hoy). Son catálogos puramente de infraestructura
(marca/modelo/dimensiones de equipo), **sin PII ni datos de dinero** — bajo riesgo para import.

- **`brands` primero, vía SmartImport (modo SMART) desde prod** — es la raíz de la cascada; sin
  marcas no se puede ni sembrar manualmente los 4 catálogos hijos desde la UI (todos exigen
  `brand_id`). Import es preferible a siembra manual aquí porque los nombres de marca reales
  (Ubiquiti, Huawei, etc.) sí importan para que el catálogo sirva en producción-como-espejo.
- **`box_types`, `trenche_types`, `active_equipment_types`, `passive_equipment_types`, vía
  SmartImport, después de `brands`** (mismo criterio: catálogos chicos, sin riesgo, dependientes
  de `brand_id`).
- **`colors`** — import opcional/de menor prioridad: desbloquea una pantalla que hoy no tiene
  adopción real (0 filas históricas en el flujo que la usa); no es urgente, pero es la misma
  familia de catálogo chico y sin riesgo si se decide incluirla en la misma pasada de `brands`.
- Los otros 7 catálogos (`contratable_packages`, `contratable_services`, `ipv6_policies`,
  `positions`, `project_types`, `social_providers`, `tube_types`) **no requieren ninguna acción** —
  vacíos es su estado correcto hoy (autopoblados, módulo en construcción, o sin consumidor real).

Cualquier import real (SmartImport contra prod) es una acción nueva, fuera del alcance
solo-lectura de este item — queda para que Irving la apruebe como paso siguiente si lo decide.
