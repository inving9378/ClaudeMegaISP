# Módulo Manual (Manual de Usuario)

> Documentación viva del sistema, generada por IA. `app/Modules/Addons/Manual/` · slug `addon-manual`
> · addon, sin dependencias, activo.

**En simple:** es el manual de usuario del sistema que se escribe solo con ayuda de IA, para que cada módulo tenga su propia página de ayuda sin que alguien tenga que redactarla a mano.

## 1. Qué es
Addon que genera y sirve la **documentación de usuario** de MegaISP: por cada módulo activo del sistema produce una sección de wiki (qué es, cómo acceder, funcionalidades, campos, permisos, preguntas frecuentes) usando el proveedor de IA configurado, y la expone en una vista `/manual` navegable más un panel de ayuda contextual disponible en cualquier pantalla.

## 2. Para qué sirve
Le resuelve a cualquier usuario del sistema (no solo a Irving/DESARROLLADOR) la necesidad de entender qué hace cada módulo sin depender de que alguien escriba y mantenga manuales a mano. Como el contenido se regenera con IA cuando el sistema cambia (nuevas migraciones, activación de un módulo nuevo), el manual tiende a no quedarse obsoleto igual que una documentación estática.

## 3. Cómo funciona
- **Modelo** `ManualSection` (tabla `manual_sections`, `App\Modules\Addons\Manual\Models\`): una fila por `module_slug` + `version` (único compuesto), con `title`, `content` (Markdown largo) y `generated_at`. Se guarda un historial versionado, no se sobreescribe la sección anterior.
- **Servicio** `ManualGeneratorService` (`Services/ManualGeneratorService.php`) — el corazón del módulo:
  - `generate(?$onlySlug)` recorre los módulos activos (tabla legacy `modules` + `module_registry` deduplicados por slug), arma un prompt por módulo con una plantilla Markdown fija (secciones: ¿Qué es?, ¿Cómo acceder?, Funcionalidades, Campos importantes, Permisos requeridos, FAQ) incluyendo un extracto de `routes/web.php`/`routes/api.php` como contexto, y llama al proveedor de IA activo vía `IAAdaptadorFactory` (módulo IA — servicio único designado del proyecto, ver `docs/modulos/ia.md`).
  - Cada corrida crea una **nueva versión** por módulo y poda automáticamente las versiones antiguas dejando solo las últimas 3 (`pruneOldVersions`) para que la tabla no crezca sin límite.
  - Usa un `Cache::lock('manual-generate-run', 3600)` para impedir corridas concurrentes (evita duplicar el gasto de tokens si el botón web y un comando/job corren a la vez).
  - Registra el consumo de tokens/costo en `ia_uso_tokens` vía `IAPricingService` (best-effort, un fallo no tumba la generación).
- **Disparadores de regeneración** (todos delegan en el mismo servicio, nunca gastan IA en paralelo gracias al lock):
  - Comando manual `php artisan manual:regenerate [--section=slug]` (`Commands/RegenerateManualCommand.php`).
  - Botón "Regenerar" en la UI → `POST /api/manual/generate` (solo si el usuario tiene el permiso `manual_generate`).
  - **Automático tras migrar:** `RegenerateManualAfterMigrate` escucha los eventos `MigrationEnded`/`MigrationsEnded` de Laravel; si `migrate` aplicó al menos una migración nueva, dispara `manual:regenerate` (síncrono, vía `Artisan::call`).
  - **Automático al activar un módulo:** `ModuleObserver` observa `ModuleRegistry` (tabla `module_registry`); cuando un módulo pasa de inactivo a activo por primera vez (sin sección previa para su slug), encola `RegenerateManualSectionJob` para generar solo esa sección. Si el módulo ya tiene manual (reactivación/toggle), se omite para no regastar tokens.
- **Frontend:** vista Blade `views/index.blade.php` monta `<manual-index>` (componente Vue `resources/js/components/module/manual/ManualIndex.vue`, registrado en `app.js`), que lista las secciones agrupadas por `group` del módulo y permite regenerar (solo visible si `is-developer`).
- **Ayuda contextual global:** `ManualController::help` resuelve, a partir de la URL actual (`?url=`), el bloque de ayuda (`terms`/`steps`/`actions`) declarado por el módulo dueño de esa pantalla — primero busca en `screens[]` de los manifiestos (`ModuleRegistry::getScreenHelp`), y si no hay match cae al bloque `doc` de `config_sections[]`. Consumido por `resources/js/components/ayuda/HelpFloat.vue`, un panel flotante disponible en **cualquier pantalla** para **cualquier usuario autenticado** (sin permiso extra, a diferencia del resto del módulo).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Vista** `GET /manual` (permiso `manual_view`, guard `check_route_permission`).
- **API JSON** bajo `api/manual/*`: `GET /sections` (todas las secciones, última versión por módulo, agrupadas), `GET /sections/{slug}` (una sección), `POST /generate` (regenera todo o un slug, permiso `manual_generate`) — estas tres bajo `check_route_permission`; `GET /help` (ayuda contextual por pantalla) bajo solo `auth` (sin gate de permiso, para que cualquier usuario logueado la use).
- **Permisos** `manual_view` (ver el manual) y `manual_generate` (regenerarlo con IA), declarados en `module.json`.
- **Comando** `manual:regenerate {--section=}` invocable desde CLI o por otros procesos (ej. el listener post-migrate).

**Consume**
- **Módulo IA** (servicio único designado) vía `IAProveedor`/`IAAdaptadorFactory`/`IAAdaptadorInterface` (`app/Modules/Addons/IA/`) para generar el contenido de cada sección con el proveedor activo configurado en `/ia/configuracion`. Sin un proveedor activo, `generate()` lanza `RuntimeException`.
- **`IAPricingService`** para calcular el costo estimado del uso y dejarlo en `ia_uso_tokens` (auditoría de gasto, no bloqueante).
- **`ModuleManagerService::manifests()`** y las tablas `modules` (legacy) + `module_registry` para descubrir qué módulos activos documentar.
- **`ModuleRegistry` (eventos Eloquent)** — `ModuleObserver` escucha sus cambios de `active` para auto-generar la sección de un módulo recién activado.
- **Eventos nativos de Laravel** `MigrationEnded`/`MigrationsEnded` para disparar la regeneración tras `php artisan migrate`.
- No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para Manual al momento de esta doc.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
