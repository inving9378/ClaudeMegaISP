# Módulo Roadmap

> Tablero de planeación del proyecto + motor del "Circuito de Mejora Continua" (Claude Code ⇄ Hoja de Ruta ⇄ Claude Cowork). `app/Modules/Addons/Roadmap/` · slug `addon-roadmap` · addon activo, oculto del sidebar (vive como pestaña dentro de Actualizaciones/Reporte del Sistema).

## 0. En simple
Es la lista de pendientes del sistema donde Claude y el equipo apuntan qué falta hacer, y un robot supervisor que va tomando esos pendientes uno por uno, los arregla solo cuando es seguro, y le avisa a Irving cuando la decisión es delicada.

## 1. Qué es
Módulo que guarda los **items de la Hoja de Ruta** (`roadmap_items`: título, descripción, estado, prioridad, prompt) y, sobre esa misma tabla, implementa el **Circuito de Mejora Continua**: un ciclo automatizado en el que Claude Code (local) audita el sistema y llena la Hoja de Ruta, Claude Cowork (nube, vía API externa sin login) revisa y clasifica el riesgo de cada item, y un conjunto de ejecutores on-box (`circuito:*` Artisan commands) reclama, implementa, verifica e integra a `main` únicamente lo aprobado y de bajo riesgo.

## 2. Para qué sirve
Le sirve a Irving y a Claude para coordinar trabajo de mejora continua sin supervisión constante: Claude Code detecta deuda técnica/bugs/documentación faltante y los registra; Claude Cowork (con acceso a internet) los revisa día a día vía la API externa y decide el `nivel_riesgo` (A/B/C); y varios agentes ejecutores en paralelo (worktrees aislados en esta misma máquina) toman los items de nivel A ya aprobados y los resuelven de punta a punta —rama, cambio, verificación, merge a `main`— sin tocar producción ni pedir intervención humana, salvo que el item sea de riesgo B/C o algo salga mal (ahí escala a `requiere_irving`).

## 3. Cómo funciona

**Piezas de datos:**
- `roadmap_items` — tabla principal (migraciones `2026_05_31_210000` + varias `add_*` de circuito). Campos clave del tablero: `title/description/status/priority/target_version/prompt/position/started_at/completed_at/subtasks/log`. Campos del Circuito (Parte 1.1+): `modulo`, `nivel_riesgo` (A/B/C), `estado_aprobacion` (`pendiente_revision → aprobado_claude/aprobado_revisor → en_progreso → completado`, o `requiere_irving`/`cancelado`), `comentarios_claude`, `revisado_at`, `aprobado_por`, `branch`/`merge_commit` (aislamiento por rama), `opciones`/`opcion_elegida` (bandeja de decisiones), `urgente`/`urgente_at`/`urgente_by` (disparo manual), `en_desarrollo_humano` (candado anti-colisión), `worker_sid` (qué worktree lo reclamó), `archivado_at`/`archivado_por`.
- `roadmap_item_memory` — memoria tipo CLAUDE.md por item (contexto acumulado entre sesiones).
- `circuito_ejecuciones` y `circuito_disparos` — bitácora de vueltas del circuito y disparos manuales.
- Permisos de control: `circuito_pausado` (kill switch), flags del revisor/disparo (Spatie permissions usadas como banderas globales, no como permisos de usuario).

**Modelo RoadmapItem** (`Models/RoadmapItem.php`) centraliza los enums válidos (`NIVELES_RIESGO`, `ESTADOS_APROBACION`) — son la fuente de verdad que valida el endpoint externo.

**Flujo del Circuito (alto nivel):**
1. Claude Code (esta sesión u otras) crea/actualiza items vía tinker o el panel, dejando `nivel_riesgo` y `prompt` (plan por fases) para cada hallazgo.
2. Claude Cowork revisa por HTTP sin login: `RoadmapExternalController` expone lectura/escritura por token en el path (allowlist de 3 campos: `estado_aprobacion`, `nivel_riesgo`, `comentarios_claude`), con variantes GET-por-query, GET-por-path y base64 pensadas para el fetcher de Cowork (que descarta query strings). También existe un conector **MCP** (`RoadmapMcpController`, JSON-RPC 2.0 sobre `/mcp/{secret}`) para custom connectors de claude.ai.
3. Un conjunto de comandos `circuito:*` (`app/Modules/Addons/Roadmap/Console/`) orquesta el ciclo on-box: `SchedulerCommand` planifica y lanza N vueltas en paralelo; `ClaimNextCommand` reclama atómicamente el siguiente item elegible para un worker; `ProvisionWorktreeCommand` prepara el worktree aislado de cada ejecutor; `RamaItemCommand`/`IntegrarItemCommand` crean la rama del item y la integran a `main` (A/B automático, C requiere a Irving); `MergeRunCommand` corre los merges encolados en el checkout principal; `WatchdogCommand`/`ReapStuckCommand` vigilan workers y liberan items atascados; `DestrabeCommand`/`RevisarBacklogCommand`/`RevisarItemCommand` (revisor adversarial, #338) re-triajean o autorizan/escalan items B; `PriorizarSeguridadCommand` sube prioridad de hallazgos de seguridad/dinero; `BriefCCommand` prepara briefs de decisión para items C; `VivoCommand` espejea a BD el heartbeat de la vuelta en curso (para la Torre de control); `DisparoCheckCommand` atiende disparos manuales.
4. Los servicios de dominio hacen el trabajo pesado: `RoadmapCircuitoService` (orquestación central: reclamar, avanzar estado, políticas de riesgo), `MergeRunner` (mecánica de git para integrar ramas), `RevisorService` (veredictos del revisor adversarial), `WatchdogService`/`SupervisorService` (salud del pool de workers), `SessionTreeService` (árbol de sesiones `claude` vivas en el box, para detectar colisiones), `RoadmapMemoryService` (memoria por item).
5. Panel admin: `RoadmapController` sirve la **Torre de control** (dashboard en vivo del circuito + kill switch + vista de Integración/Ramas + bandeja de decisiones) y el CRUD clásico de items (listar/crear/editar/start/complete/cancel/subtareas/bitácora), consumido por la pestaña "Hoja de ruta" dentro de Actualizaciones (URL `/releases`).

**Reglas de negocio duras (aplicadas por el propio código, no solo documentales):** solo un item `in_progress` a la vez (tablero clásico); niveles de riesgo A (aditivo/reversible, auto-ejecutable) / B (requiere confirmación de Irving) / C (decisión exclusiva de Irving); kill switch `circuito_pausado` y flag de revisor son solo-lectura para los ejecutores; nunca `push`/`migrate:fresh`/toca prod desde el circuito.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **API externa sin login** (`/api/roadmap-externo/{token}/...`, throttle por config, fuera de menús/sitemap): lectura de items (individual o por query estado/nivel/página) y escritura acotada (allowlist `estado_aprobacion`/`nivel_riesgo`/`comentarios_claude`) — consumida por **Claude Cowork**.
- **Conector MCP** (`POST /mcp/{secret}`, JSON-RPC 2.0) — para custom connectors de claude.ai.
- **API interna autenticada** (`/api/roadmap/*`, `web`+`auth`): Torre de control (`/torre`, `/circuito/estado`, `/circuito/sesiones`), disparo manual (`/circuito/disparar`), toggle del kill switch (`/circuito/toggle`), bandeja de decisiones (`/circuito/decidir`, `/circuito/seguimiento`), vista de Integración/Ramas (`/integracion*`: merge/rechazar/revert/modo/marcar-version/historial/archivar/desarchivar), CRUD de items (`GET/POST/PATCH/DELETE /items*`, start/complete/cancel, subtareas, bitácora), memoria por item (`/items/{id}/memory*`).
- Permisos Spatie `roadmap_view`/`roadmap_manage` (declarados en `module.json`) — gatean el CRUD interno del tablero.
- **Comandos Artisan `circuito:*`** (18 comandos) — es la superficie que consumen el scheduler/cron y los ejecutores on-box (incluida esta misma sesión) para operar el ciclo.
- Pestaña "Hoja de ruta" dentro de Actualizaciones/Reporte del Sistema (Vue, vía `screens.roadmap_tab` de `module.json`).

**Consume**
- **Git del propio repositorio** (vía `MergeRunner`/`RamaItemCommand`) — crea ramas `circuito/item-<id>-<slug>`, hace merge a `main` en el checkout principal; nunca hace `push` a `origin` ni toca producción.
- **Sesiones de shell `claude` vivas en el box** (`SessionTreeService`) — para detectar colisiones entre agentes/ejecutores concurrentes.
- **Cola `database`** — algunos flujos del circuito corren como jobs/heartbeats (`VivoCommand`) para alimentar la Torre en vivo.
- Tokens de la API externa y del conector MCP (`ROADMAP_EXTERNAL_READ_TOKEN`/`WRITE_TOKEN`, secreto MCP) — viven en `.env`, **nunca** en código ni docs versionados.
- No depende de otro módulo de negocio (clientes/facturación/etc.) — es infraestructura de proceso, autocontenida.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
