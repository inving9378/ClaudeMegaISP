# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**MegaISP / Meganet Telecomunicaciones** — an ISP (Internet Service Provider) management system. The application handles clients, billing/invoicing, CRM/prospects, plans (Internet, VoIP, Custom, Bundle), MikroTik router integration, OLT/ONU management, fiber network mapping, ticketing, inventory, sellers/commissions, and scheduling. Codebase and UI are in **Spanish** — preserve Spanish naming when adding new domain code.

Stack: **Laravel 10** (PHP ^8.1, target PHP 8.2.17) + **Vue 3 Composition API** + **Quasar** UI + **Laravel Mix** (webpack). MySQL is the primary DB; an optional second `radius` connection backs FreeRADIUS consumption stats.

## Common commands

```bash
# Backend
composer install
cp .env.example .env && php artisan key:generate && php artisan storage:link
php artisan migrate                       # 300+ migrations — order matters
php artisan serve                         # dev HTTP server

# Frontend (Laravel Mix / webpack)
npm install
npm run dev                               # one-shot build
npm run watch                             # rebuild on change
npm run watch-poll                        # use when inotify is unreliable (Docker/WSL)
npm run prod                              # production build (versioning + drop_console)

# Testing
php artisan test                          # runs Feature + Unit
php artisan test tests/Feature/ClientTest.php          # single file
php artisan test --filter=test_activate_client         # single test method

# Scheduling / queues
php artisan schedule:run                  # invoked by cron every minute in prod
```

**Test environment caveat:** `tests/TestCase.php` runs `migrate:fresh --seed` in `setUp()` — every test wipes and re-seeds the DB. Point `APP_ENV=test` at a separate database (e.g. `meganet_test`) before running tests, never the dev/prod DB. Most tests in `tests/Feature/ClientTest.php` are currently commented out.

## Architecture

### Backend layout

- `app/Http/Controllers/Module/<Domain>/` — controllers are organized by business module (Client, Crm, Finance, Network, OLTs, Router, Mapas, Maps, Inventory, Scheduling, Sellers, Setting, Ticket, Vendors, Plan, Message, IA, Release, Shared). New endpoints belong under the matching `Module/` subfolder, **not** at the top of `Controllers/`.
- `app/Http/Repository/` — repository layer wrapping Eloquent queries (e.g. `ClientRepository`, `ModuleRepository`). Controllers and services call repositories rather than models directly for non-trivial queries.
- `app/Services/` — domain services (BillingService, MikrotikService, OLTsService, InvoiceService, NetworkIpService, etc.). Heavier orchestration lives here.
- `app/Http/Traits/` and `app/Http/Traits/Models/` — shared model behavior; e.g. `Client` uses `ClientTrait` + `ScopeClient`. When extending a domain model, check for an existing trait before adding methods directly.
- `app/Models/BaseModel.php` — all domain models extend this. Models auto-stamp `created_by`/`updated_by` from `auth()->user()` in boot hooks.

### Route + permission system

- `routes/web.php` is the main router (~1750 lines) — almost all module routes are inside `Route::group(['middleware' => ['auth', 'check_route_permission']])`. `routes/api.php` is small; `routes/script_db.php` is included from `web.php` for one-off DB scripts.
- **`check_route_permission`** (`app/Http/Middleware/CheckRoutePermission.php`) is the custom authorization middleware that gates every module route by URL. It uses **spatie/laravel-permission** for the underlying roles/permissions and has a `PUBLIC_ROUTES` constant whitelist for routes that must skip permission checks (image endpoints, helper lookups). Add a route to `PUBLIC_ROUTES` only when it genuinely needs to bypass per-user permissions.
- Front-end mirror: Vuex store (`resources/js/store.js`) loads the current user's permissions via `GET /permissions-auth` on app boot, and the `v-hasPermission` directive (registered in `app.js`) hides UI elements. The Vue app waits on `store.dispatch('fetchPermissions')` before mounting on `#init-vue`.

### Frontend architecture

- **Single Vue app, globally-registered components.** `resources/js/app.js` imports every page-level Vue component and registers them on one `createApp({ components: { … } })`. Blade templates (under `resources/views/meganet/`) reference these components by kebab-case tags inside `<div id="init-vue">…</div>` (see `resources/views/meganet/layout/master.blade.php`). When adding a new screen: create the `.vue` file under `resources/js/components/module/<domain>/`, then import + register it in `app.js` — it won't render otherwise.
- **Quasar** is loaded from `public/plugins/quasar/js/quasar.umd.prod` (not from npm). Components must be added to the `app.use(Quasar, { components: [...] })` list in `app.js`. Icon set is FontAwesome v5.
- **Laravel Mix** (`webpack.mix.js`) compiles `resources/js/app.js` → `public/js/app.js` and `resources/sass/app.scss` → `public/css/app.css`. Production build adds `mix.version()` (cache busting via `mix-manifest.json`) and drops `console.*`.
- Vuex store is intentionally minimal (just permissions). Component state is local; cross-component communication happens via props/emits or Axios calls.

### Module system (configurable modules)

The `modules` table is a central concept. Several features (import/export, dynamic columns, dynamic fields, datatable customization) are driven by module records. When adding a new module to the import system, follow `README_DOC.md`:

1. Add the module name to `ModuleRepository::MODULES_FOR_IMPORT`.
2. Implement `getRequestAndStoreMethod()` on the corresponding Eloquent model, returning the FormRequest + controller@store route + optional `parameter_id`.
3. Add validation rules in `ComunConstantsController::RULES`. For dynamic rules (e.g. uniqueness across an upload), extend `App\Http\Traits\ValidationImportModuleTrait::getRulesWithModel`.
4. Optionally set column export order via `ComunConstantsController::ORDER_COLUMNS_MODULE_TO_EXPORT_EXCEL`.

### Scheduling & background commands

`app/Console/Kernel.php` does **two** things:

1. **Dynamic schedule from DB** — reads `command_configs` rows via `CommandConfigRepository` and registers each enabled command with its configured frequency/time. Admins can enable/disable scheduled jobs from the UI without code changes.
2. **Hard-coded schedule** for critical jobs: `invoice:create-proformas` (daily 03:00), `app:mikrotik-sync-command` (every 5m), `mikrotik:sync-consumption` (every 10m), `mikrotik:sync-ping` (every 5m), `smartolt:sync-inventory` (daily 05:00), `smartolt:sync-critical` (every 10m), `activitylog:archive --days=90` (daily 02:00 — moves old `activity_log` rows to a separate `meganet_logs` DB).

Commands are autoloaded from three folders: `app/Console/Commands/Active/` (production schedule), `app/Console/Commands/Olts/`, and `app/Console/Commands/Scripts/` (one-off data fixes — review before running, several are destructive).

### External integrations

- **MikroTik RouterOS** via `pear2/net_routeros` — wrapped in `MikrotikService`. Toggle with `ROUTER_LOCAL` and `CONECTION_MIKROTIK` env vars (set `false` to no-op in environments without router access).
- **FreeRADIUS** — optional second DB connection (`DB_RADIUS_*` env). Used by `InternetConsumptionRadiusController` for traffic stats.
- **OLT / SmartOLT** — `app/Services/OLTsService.php` + `Module/OLTs/` controllers.
- **Asterisk AMI** for VoIP (`AMI_*` env).
- **Google Maps + Leaflet** — `MIX_VUE_APP_GOOGLEMAPS_KEY` exposed via Mix. Leaflet is used for fiber network / OSP mapping (extensive plugin set in `package.json`).
- **WhatsApp Evolution API** (`WHATSAPP_API_*` env).
- **Claude API** — `app/Http/Controllers/Module/IA/IAChatController.php` powers an in-app chat assistant. Reads `CLAUDE_API_KEY` and `CLAUDE_MODEL` from env. The system prompt instructs Claude to respond with `{"action": ..., "params": ..., "message": ...}` JSON for system actions (search client, list tasks, etc.) and plain Spanish text otherwise.

### Geo seed data (`states`, `municipalities`, `colonies`)

These tables are populated from SQL dumps in `config/state_municipalities_and_colonies/`. When importing a new dump, follow the SQL-rewrite steps in `README_DOC.md` — the `colonies` table requires `ALTER TABLE` instead of `CREATE TABLE` and the explicit `PRIMARY KEY (id)` line must be removed, otherwise migrations fail.

### Index optimization helper

`App\Services\CheckIndexService` takes an associative array of `table => [columns]`, returns the columns missing indexes (`checkMissingIndexes`) and can create them (`addMissingIndexes`). Use this when adding new query-heavy code to ensure the relevant columns are indexed.

## Conventions

- **Spanish domain vocabulary** (`cliente`, `pago`, `factura`, `vendedor`, `morosos`, `red`, `caja`, `colonia`). Keep new domain code in Spanish to match.
- The `check_route_permission` middleware enforces permissions by URL path — when adding a controller method, also register a corresponding row in the permissions table (existing migrations in `database/migrations/` show the pattern, e.g. `add_permision_administration_documentation*`).
- Don't bypass the repository layer for non-trivial queries — extend the existing `*Repository` class.
- `BaseModel` auto-fills `created_by`/`updated_by`. Don't set them manually on save.
- After changing `webpack.mix.js` or adding a Vue component, rebuild with `npm run dev` (or have `npm run watch` running) so `mix-manifest.json` and `public/js/app.js` are up to date.
