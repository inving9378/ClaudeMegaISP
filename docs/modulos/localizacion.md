# Módulo Localización

> Catálogo geográfico y administrativo del sistema: estados, municipios, colonias, sucursales y ubicaciones. `app/Modules/Core/Localizacion/` · slug `core-localizacion` · módulo **core**, activo.

## 0. En simple
Es el directorio de "dónde": guarda la lista de estados, municipios y colonias de México, las sucursales de la empresa, y ubicaciones generales — y se lo presta a las fichas de clientes, CRM y otros formularios para que puedan elegir la dirección de alguien con esos mismos selects.

## 1. Qué es
Módulo **core** (sin dependencias declaradas) que centraliza las entidades geográficas y administrativas básicas: `State` (estado), `Municipality` (municipio), `Colony` (colonia), `Sucursal` (sucursal de la empresa) y `Location` (ubicación genérica). No es un módulo con lógica de negocio propia — es un catálogo compartido, con CRUD estándar sobre cada entidad.

## 2. Para qué sirve
Le da a todo el sistema un catálogo único de estado/municipio/colonia para capturar direcciones de forma consistente (en vez de que cada módulo escriba texto libre), evitando duplicar listas geográficas. También administra las **sucursales** físicas de la empresa (para asignar usuarios/técnicos a una sucursal) y una entidad **Location** genérica usada como punto de referencia de infraestructura de red (relacionada 1-a-1 con `Router` y `Network`).

## 3. Cómo funciona

**Entidades y tablas** (`app/Models/`, fuera del namespace del módulo — el módulo solo aporta controllers/rutas sobre modelos ya existentes en `app/Models`):
- `State` (`states`) — catálogo de estados. `BaseModel`, sin FK.
- `Municipality` (`municipalities`) — pertenece a un `State` (`belongsTo`).
- `Colony` (`colonies`) — pertenece a un `Municipality` (`belongsTo municipio`).
- `Sucursal` (`sucursals`) — sucursal de la empresa (nombre/email/teléfono/dirección); consumida por `User` (`users.sucursal_id`, migración `2026_01_04_065931_add_sucursal_to_users`) para asignar cada usuario/técnico a una sucursal.
- `Location` (`locations`) — tabla mínima (id/name); relación `hasOne` con `Router` y `Network` (infraestructura de red), NO con geografía.

`states`/`municipalities`/`colonies` se poblaron originalmente desde dumps SQL en `config/state_municipalities_and_colonies/` (`states.sql`, `municipalities.sql`, `colonies.sql`), no desde una migración Laravel tradicional (ver sección "GEO DATA" del `CLAUDE.md` del repo).

**Controllers** (`Controllers/`), todos CRUD estándar (index/store/update/destroy + `table` para el datatable):
- `StateController`, `MunicipalityController` (con filtro por `state_id`), `ColonyController` (con filtro por `municipality_id`) — los dos últimos extienden `CrudModalController` (modal reusable) con un `DatatableHelper` propio por entidad.
- `SucursalController` — CRUD de sucursales + endpoint `all()` (lista completa sin paginar, para selects).
- `LocationController` — CRUD de ubicaciones genéricas.
- `ComponentSelectStateMunicipalityAndColonyController` — controller auxiliar de un solo método (`getValueDB`), resuelve el valor de un campo geográfico (`state_id`/`municipality_id`/`colony_id`) para un registro dado de `ClientMainInformation`, `CrmMainInformation` o `CompanyInformation`, según el modelo que se le pase por parámetro.

**Consumidores reales del catálogo geográfico:** las tablas `client_main_information`, `crm_main_information` y `company_information` tienen sus propias columnas `state_id`/`municipality_id`/`colony_id`/`location_id` — el módulo no dueña esas filas, solo provee el catálogo al que apuntan esas FKs y el CRUD de administración de dicho catálogo.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web** bajo `['web','auth','check_route_permission']`, prefijo `/administracion/*` (preservado por compatibilidad con el frontend legacy): `ubicacion/*` (Location), `sucursal/*` (Sucursal, + `sucursal/all`), `estado/*` (State), `municipio/*` (Municipality), `colonia/*` (Colony) — cada uno con `/`, `/add`, `/editar/{id}`, `/update/{id}`, `/destroy/{id}`, `/table`.
- **Endpoint helper global** `POST /helper/get-value-colony-state-municipality` (sin prefijo, para compatibilidad) — usado por el frontend (`resources/js/helpers/Request.js::getValueDB`) desde el componente compartido `Select2EstadoMunicipioColoniaComponent.vue` (el selector cascada Estado→Municipio→Colonia que usan los formularios dinámicos de cliente/CRM/empresa) para precargar el valor actual al editar.
- **5 tarjetas de administración** (`module.json` → `admin_cards`): Ubicaciones, Sucursales, Estados, Municipios, Colonias — cada una gateada por su propio permiso Spatie (`state_view_state`, `municipality_view_municipality`, `colony_view_colony`, `view_sucursal`, `admin_view_meganet`).
- **Sección de configuración** "Repoblar Estado/Municipio/Colonia" (`config_sections` → `repoblar_geo`, permiso `config_view_tools`) — recarga los catálogos geográficos desde los archivos fuente sin borrar registros existentes (documentación en el propio `module.json`, ejecución vía la pantalla `/administracion/set-state-municipality-and-colony`).
- **Modelos compartidos** `App\Models\State`, `Municipality`, `Colony`, `Sucursal`, `Location` — consumidos directamente (Eloquent) por cualquier módulo que necesite resolver o filtrar por geografía/sucursal (p. ej. `User::sucursal()` belongsTo `Sucursal`).

**Consume**
- **`ClientMainInformation` / `CrmMainInformation` / `CompanyInformation`** (Core Clientes/CRM/Configuración) — el controller auxiliar `getValueDB` lee estas tablas para resolver el valor geográfico actual de un registro; el módulo no las modifica.
- **Sistema de permisos Spatie** (`check_route_permission` + `permission` en `admin_cards`/`config_sections`) — gating estándar de todas las rutas y accesos de UI.
- **Dumps SQL en `config/state_municipalities_and_colonies/`** — fuente de datos para poblar/repoblar el catálogo geográfico (fuera del ciclo normal de migraciones).

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
