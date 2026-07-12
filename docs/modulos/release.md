# Módulo Release

> Changelog/release notes del sistema + pipeline de publicación. `app/Modules/Core/Release/` · slug `core-release` · core, sin dependencias, siempre activo.

**En simple:** es la pantalla donde se crea una nueva versión del sistema, se escribe qué cambió, y desde ahí se dispara todo el proceso automático que respalda la base de datos, sube el código a GitHub y actualiza el servidor.

## 1. Qué es

Módulo Core que lleva el registro de versiones (`releases`) liberadas del sistema, sus notas de cambio (`release_descriptions`), y orquesta el pipeline de publicación (`deployment_logs` + `DeploymentService`) que respalda, commitea, tagea y despliega cada versión.

## 2. Para qué sirve

- Le da a Irving (y a cualquier admin con permiso) una pantalla (`/releases`) para crear una versión nueva, redactar o generar con IA sus notas de cambio, y ver el historial de versiones/deploys pasados.
- Es el punto de entrada del pipeline de release: al crear una versión dispara `DeployJob` (respaldo de BD → git add/commit/tag/push → GitHub Release → deploy remoto en prod), con progreso visible en vivo (modal de polling).
- Aloja también, en la misma pantalla `/releases`, el panel operativo del **Circuito CC** (pestañas Roadmap, Torre de Control, Torre de Terminales, Integración de Ramas) y un reporte de auditoría del sistema en vivo — no son parte del versionado en sí, comparten pantalla por conveniencia de administración.

## 3. Cómo funciona

- **Controllers**: `ReleaseController` (CRUD de versiones, `nextVersion()` sugiere el siguiente número `V{mayor}.{menor}` con fecha, `generateChangelog()` delega en IA, `redeploy()` re-lanza el pipeline de una release "fantasma" cuyo tag nunca se creó en git), `ReleaseDescriptionController` (CRUD de notas asociadas a una release), `DeploymentController` (estado/log en vivo de un deploy vía polling, historial paginado, `retry()` de un deploy fallido), `AuditController` (checklist de plan de auditoría en `audit_plan_items` + reporte en vivo de salud del sistema — KPIs, workers, cola, disco, integraciones).
- **Modelos/tablas**: `Release` (`releases`: version/title/summary/release_date), `ReleaseDescription` (`release_descriptions`: notas por release), `DeploymentLog` (`deployment_logs`: status/steps JSON/payload/duración — `updateStep()` actualiza un paso del pipeline en vivo), `AuditPlanItem` (`audit_plan_items`: checklist con notas, sembrado con un plan default si está vacío).
- **Pipeline de publicación** (`App\Services\Deploy\DeploymentService`, invocado por `App\Jobs\DeployJob` con `DeploymentLock` para serializar deploys): pasos configurables en `config/deployment.php` — `db_backup` (respaldo streaming) → `git_check_secrets`/`git_staging_gate` (allowlist de artefactos, NUNCA `git add -A`) → `git_add`/`git_commit`/`git_tag`/`git_push` → `github_release` (crea el release en GitHub con las notas) → `remote_deploy` (tipo `http`, dispara el pipeline remoto en prod). Varios pasos se saltan fuera de producción (`skip_if_not_production`, item #245) para que crear una versión en dev **no** publique de verdad.
- **Generación de changelog con IA**: `App\Services\ReleaseChangelogService` junta commits/diff stat desde el último tag (excluyendo patrones sensibles como `.env`/`.pem`/`secret`) y pide a Claude un título/resumen/mejoras estructurados.
- **Frontend**: página única `resources/views/meganet/module/releases/index.blade.php` → `<releases-index>` (Vue), que compone internamente `ReleasesCrud`, `AuditReport`, `RoadmapTab`, `TorreControl`, `TorreTerminales`, `IntegracionRamas` y `DeployProgressModal` (import local, no globales en `app.js`).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- Rutas bajo `/releases/*` (`['web','auth','check_route_permission']`): listar/crear/editar versiones, `next-version`, `redeploy/{id}`, notas (`description/*`), `generate-changelog`, reporte de auditoría (`audit/report`, `audit/plan/*`) e historial/estado/log/retry de deploys (`deployments`, `deployment/{id}/status|log|retry`).
- Permisos: `release_view_release`, `release_add_release`, `release_edit_release`, `release_add_description`, `release_edit_description`, `release_delete_description` (mapeados en `config/route_permission.php`).
- `App\Jobs\DeployJob` (cola `deploy`, conexión `database`) — el punto de entrada asíncrono del pipeline; lo dispara este módulo pero corre fuera del ciclo de request.
- Tablas `releases`/`release_descriptions`/`deployment_logs`/`audit_plan_items` como fuente de verdad del historial de versiones y deploys.

**Consume**
- `App\Modules\Addons\Marketing\Services\ClaudeApiClient` (vía `ReleaseChangelogService`) para redactar el changelog — usa el cliente de IA de Marketing en vez del módulo IA designado (`ia_proveedores`/`/ia/configuracion`); no monta su propio cliente HTTP.
- Webhook remoto (`config('deployment.remote_url')`, tipo `http`) — dispara `RemoteDeployCommand` en el servidor de producción para el paso `remote_deploy`.
- API de GitHub (paso `github_release`) para publicar el release con sus notas.
- Middleware estándar `check_route_permission` (mismo gating que el resto del sistema, sin verificador propio).
- Métricas de otros módulos solo-lectura en `AuditController::generate()` (tablas `invoices`/`client_invoices`, `marketing_generated_content`, `marketing_messages`, `failed_jobs`/`jobs`, `api_integrations`, Evolution API vía `WHATSAPP_API_URL`) — es un panel de diagnóstico cross-módulo, no una dependencia funcional.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
