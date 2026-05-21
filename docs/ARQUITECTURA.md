# Arquitectura — MegaISP

> Documento de referencia estable. Si vas a abrir un editor o un agente IA en este repo, **lee esto primero**.

---

## Regla #1 — Único árbol activo

| Path                  | Estado                                                          |
| --------------------- | --------------------------------------------------------------- |
| `/var/www/megaisp`    | **Único árbol activo.** nginx lo sirve en `:80`. Todo desarrollo va aquí. |
| `/var/www/MEGANET`    | Árbol de **referencia / código legacy**. NO se desarrolla aquí. NO se commitea aquí. Su vhost nginx (`/etc/nginx/sites-enabled/meganet.conf`) está deshabilitado desde 2026-05-20. |

**Si trabajas en MEGANET porque ahí está el código a portar:** úsalo como fuente de lectura, escribe el resultado en `megaisp/app/Modules/Addons/<NuevoModulo>/`. Nunca commitees nuevos features al árbol MEGANET.

---

## Estructura modular (`app/Modules/`)

Migración modular completada 2026-05-20. Sin `nwidart/laravel-modules` — implementación propia.

```
app/Modules/
├── BaseModuleServiceProvider.php   ← NO modificar
├── Core/                            ← módulos del sistema (Auth, Clientes, etc.)
│   ├── Auth/
│   ├── Clientes/
│   ├── Configuracion/
│   ├── CRM/
│   ├── Dashboard/
│   ├── Documentacion/
│   ├── Documentos/
│   ├── Layout/
│   ├── Localizacion/
│   ├── ModuleManager/
│   ├── Release/
│   ├── Usuarios/
│   └── Auditoria/
└── Addons/                          ← módulos opcionales
    ├── DevTools/
    ├── Finanzas/
    ├── GestionRed/
    ├── IA/
    ├── Inventario/
    ├── Mapas/
    ├── MegaFamilia/
    ├── Mensajes/
    ├── Planes/
    ├── Reportes/
    ├── Scheduling/
    ├── Tickets/
    └── Vendedores/
```

### Anatomía de un módulo

```
app/Modules/Addons/<NombreModulo>/
├── module.json                   ← manifiesto (slug, type, active)
├── ModuleServiceProvider.php     ← extiende BaseModuleServiceProvider; auto-discovery
├── routes.php                    ← rutas con middleware ['web','auth','check_route_permission']
├── Controllers/
├── Services/
├── Models/                       ← opcional (los modelos compartidos quedan en app/Models)
├── migrations/                   ← auto-cargadas por el provider
└── views/                        ← namespace = slug (view('addon-mimodulo::miview'))
```

### Auto-discovery

Los `ModuleServiceProvider` se descubren solos vía `ModuleManagerService`. **No registrar manualmente en `config/app.php`.** El provider sólo activa el módulo si `module.json.active === true` y la fila correspondiente en la tabla `module_registry` (o equivalente) está habilitada.

---

## Backend

### Rutas y permisos

- **Middleware obligatorio:** `['web', 'auth', 'check_route_permission']`
- `check_route_permission` (custom, no spatie gates) consulta `config/route_permission.php` por path
- Por debajo usa `spatie/laravel-permission` para roles/permissions
- Excepciones públicas: `CheckRoutePermission::PUBLIC_ROUTES` — agregar SOLO si el endpoint debe bypassear permisos
- Alternativa fina: `permission:<nombre>` middleware spatie directamente (usado por `Addons/IA/routes.php`). Convivencia válida; preferir `check_route_permission` para mantener consistencia URL-driven.

### Modelos

- `app/Models/BaseModel.php` → todos los modelos lo extienden
- Auto-stamp de `created_by` / `updated_by` desde `auth()->user()` — **nunca setearlos manualmente**
- Modelos compartidos viven en `app/Models/` (no en módulos) — accesibles vía `use App\Models\X` desde cualquier módulo
- Modelos exclusivos de un módulo: `app/Modules/<Tipo>/<Nombre>/Models/` (ejemplo: `Addons/IA/Models/IAProveedor.php`)
- **Shims activos:** 15 archivos `app/Models/Client*.php` extienden `Core/Clientes/Models/` — NO eliminar hasta migrar Fase 5 (339 refs externas)

### Repositorios y servicios

- `app/Http/Repository/` — capa de queries Eloquent (`ClientRepository`, `ModuleRepository`, etc.)
- `app/Services/` — orquestación de dominio (`BillingService`, `MikrotikService`, `OLTsService`, …)
- Controllers → call → Repositories / Services, no directo a Models para queries no triviales

---

## Frontend

### Single Vue app, componentes globales

- **No hay Vue Router.** Las rutas son Laravel; las vistas son Blade; cada Blade monta uno o más componentes Vue por tag kebab-case.
- `resources/js/app.js` importa cada componente raíz y lo registra en `createApp({ components: { … } })`.
- Las Blade views (bajo `resources/views/`) hacen `<smart-import></smart-import>` dentro de `<div id="init-vue">`.

```js
// resources/js/app.js
import ImportExportHistory from "./components/module/setting/ImportExportHistory.vue";
// ...
const app = createApp({
    components: {
        // ...
        'import-export-history': ImportExportHistory,
    },
});
```

### Quasar

- Cargado desde `public/plugins/quasar/js/quasar.umd.prod` (UMD, **no npm**)
- Cada componente Quasar que uses (`QTable`, `QDialog`, etc.) debe estar en la lista `app.use(Quasar, { components: [...] })` de `app.js`
- Iconos: FontAwesome v5 + Material Icons. **No Tabler.**

### Build (Laravel Mix)

```bash
npm run dev      # one-shot, sourcemaps
npm run watch    # rebuild on change
npm run prod     # producción minificada, mix.version() cache-busting
```

- Output: `public/js/app.js` + `public/css/app.css` + `public/mix-manifest.json`
- Después de añadir/modificar un componente Vue, **siempre** rebuild.

### Permisos en frontend

- Vuex store mínimo: `resources/js/store.js` carga permisos vía `GET /permissions-auth` en boot
- Directiva `v-hasPermission` oculta elementos UI
- La app Vue espera a `store.dispatch('fetchPermissions')` antes de montar en `#init-vue`

---

## Cómo crear un nuevo addon (checklist)

```
1. mkdir -p app/Modules/Addons/<NuevoModulo>/{Controllers,Services,Models,migrations,views}
2. Crear module.json:
   {"slug":"addon-<kebab>","name":"<Nombre>","version":"0.1.0","type":"addon","active":true}
3. Crear ModuleServiceProvider.php que extienda BaseModuleServiceProvider con
   $moduleSlug = 'addon-<kebab>' y $viewNamespace = 'addon-<kebab>'
4. Crear routes.php:
   Route::middleware(['web','auth','check_route_permission'])
       ->prefix('<prefijo>')->group(function () { ... });
5. Crear Controllers, Models, Services bajo su carpeta — namespace App\Modules\Addons\<NuevoModulo>\...
6. Migraciones bajo migrations/ (auto-cargadas)
7. Views Blade bajo views/ — referenciar como view('addon-<kebab>::nombre')
8. Vue components: resources/js/components/module/<dominio>/<NombreVue>.vue
9. Registrar el Vue en resources/js/app.js (import + entry kebab-case en components{})
10. Permisos: agregar entries en config/route_permission.php (clave = permission name, valor = lista de paths)
11. php artisan migrate
12. npm run dev
13. Visitar la URL — debe responder, no 404
```

---

## Cómo portar código desde `/var/www/MEGANET`

Cuando encuentres una feature útil en MEGANET que aún no está en megaisp:

1. **Identifica el alcance:** controllers, services, models, migrations, vistas blade, Vue components, registros en `app.js`, entradas en `config/route_permission.php`, rutas en `routes/web.php`.
2. **Crea el scaffold modular** en `megaisp/app/Modules/Addons/<NuevoModulo>/` según el checklist anterior.
3. **Adapta namespaces** al portar:
   - `App\Http\Controllers\Module\<X>` → `App\Modules\Addons\<X>\Controllers`
   - `App\Services\Y` → `App\Modules\Addons\<X>\Services\Y` (si Y es exclusivo del módulo)
   - `App\Models\Z` → mantener en `App\Models\Z` si es compartido; mover a `App\Modules\Addons\<X>\Models\Z` si es exclusivo
4. **Migraciones:** copiar bajo `Modules/<X>/migrations/` con el mismo timestamp (o uno nuevo si la tabla ya existe en otra forma). El provider las carga automáticamente.
5. **Vue components:** copiar a `resources/js/components/module/<dominio>/` y registrar en `resources/js/app.js`. **No copies legacy assume-unchanged tricks** — en megaisp `app.js` se commitea normal.
6. **Blade views:** mover a `Modules/<X>/views/` y referenciar como `view('addon-<slug>::nombre')` desde el controller.
7. **Rutas:** declarar en `Modules/<X>/routes.php` (NO tocar `routes/web.php`).
8. **Permisos:** agregar entries a `config/route_permission.php` para los URLs nuevos.
9. **Verificación:**
   - `php artisan route:list --path=<prefijo>` → debe listar las rutas
   - `php artisan migrate` → corre las migraciones nuevas
   - `npm run dev` → bundle incluye el componente (`grep -c '<NombreComponente>' public/js/app.js`)
   - Visita la URL — 200, no 404 ni 403

---

## Convenciones de Git

- **Commits selectivos por scope.** `git add <ruta/específica>`. **Nunca** `git add -A` ni `git add .` — el `.gitignore` está controlado pero el árbol untracked tiene muchos archivos generados.
- Formato de mensaje: `tipo(scope): descripción` (en español o inglés técnico). Tipos comunes: `feat`, `fix`, `chore`, `refactor`, `docs`, `test`.
- Branch principal: `main`. Branch activo de migración modular: `feature/modular-arch`.

---

## Migraciones pendientes desde MEGANET (referencia)

Si necesitas portar uno de estos, ya hay un esqueleto funcional en MEGANET:

| Feature MEGANET                                          | Destino sugerido en megaisp                              |
| -------------------------------------------------------- | -------------------------------------------------------- |
| Smart Import/Export + History UI                         | `app/Modules/Addons/SmartImportExport/`                  |
| Evaluador de Servicios Empresariales                     | `app/Modules/Addons/Vendedores/` (sub-feature) o nuevo Addon |
| DevTools chat con storage físico                         | Ya existe `app/Modules/Addons/DevTools/` — ver si extender o reescribir |

Lista no exhaustiva — revisar `/var/www/MEGANET/app/Modules/` y branches `ia-module-migration` para más candidatos.

---

## Tests

- `tests/TestCase.php::setUp()` corre `migrate:fresh --seed` — apunta `APP_ENV=test` a una DB separada (`meganet_test`), nunca dev/prod
- `php artisan test --filter=<nombre>` para un test individual
- Suite incompleta — Feature tests de `ClientTest.php` mayormente comentados
