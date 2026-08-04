# Torre de Control — Flujo REAL del circuito (auditoría de solo lectura)

> **Qué es este documento.** Una radiografía de **cómo funciona hoy** la Torre de Control y el
> circuito de items, tal como está el código en `main` (`/var/www/megaisp`), no como debería ser ni
> como dice la teoría. Todo lo afirmado está verificado leyendo código, migraciones, comandos,
> componentes Vue, la base de datos real y los logs de ejecución.
>
> **Fecha de la auditoría:** 2026-07-14. **Rama base:** `main` @ `7a33a7ac`.
>
> El documento separa a propósito tres planos que NO deben mezclarse:
> **① ASÍ FUNCIONA HOY** · **② PROBLEMAS DETECTADOS** · **③ CÓMO DEBERÍA MEJORAR**.
> Las secciones 1–11 son plano ①. Las secciones "Problemas" y "Mejoras" del final son ② y ③.
> **No se modificó código, ni BD, ni git. No se ejecutó ningún worker ni merge.**

---

## 1. Resumen ejecutivo

La "Torre de Control" es un tablero web (pestaña **Releases** del ERP) que sirve de cabina a un
**circuito autónomo de mejora continua**: un enjambre de sesiones de Claude Code (los *workers*) que,
sin intervención humana, toman items de una hoja de ruta, escriben código, lo verifican y lo integran
a `main`, escalando a Irving **solo** lo que es peligroso o requiere una decisión de diseño.

Piezas reales:

- **El motor** vive en el módulo `app/Modules/Addons/Roadmap/`: el modelo `RoadmapItem`, el servicio
  `RoadmapCircuitoService`, el `MergeRunner`, el `RevisorService`, y ~21 comandos `circuito:*`.
- **La cabina (UI)** vive en `resources/js/components/module/releases/torre-control/` (Vue). **Ojo:
  el nombre "Core/Release" es changelog/deploy, NO la Torre.** Todo el backend de la Torre es del
  módulo Roadmap.
- **Los obreros** son procesos `claude -p` (modelo Sonnet por defecto) corriendo en **worktrees git
  aislados** (`/home/meganet/circuito/wt-1 … wt-6`), lanzados por `deploy/circuito/vuelta.sh` +
  `prompt-item.txt`, orquestados por **cron cada minuto** (`circuito:scheduler`).

El hallazgo central es que **un item se describe por TRES ejes independientes** (ver §2), y casi todos
los problemas del circuito nacen de confundirlos: aprobar una **decisión** no es lo mismo que
autorizar un **merge**, y el estado que ves en la UI muchas veces **no** es el valor real de la BD
(12 discrepancias documentadas en §6 y en el mapa de traducción).

**Salud actual (foto):** el circuito integra bien el trabajo seguro (nivel A y B verificable), pero
tiene un **bucle sistémico** en items nivel C y de dinero/prod: el planificador vuelve a "reclamar"
items que ya están aprobados por Irving pero que **nunca podrán auto-integrarse**, y el worker los
re-escala una y otra vez. De los 7 items en bucle auditados, **6 siguen girando** y solo 1 (#197)
salió, porque su merge sí llegó a completarse.

---

## 2. El concepto que lo explica todo: TRES ejes de estado

No existe un único "estado" del item. Hay **tres columnas distintas**, y un item vive en las tres a la
vez. Confundirlas es la raíz de casi todo el ruido de la Torre.

| Eje | Columna en BD | Para qué sirve | Valores | Quién lo mueve |
|---|---|---|---|---|
| **Estado técnico** (Kanban) | `status` | En qué punto del tablero clásico está | `pending`, `in_progress`, `done`, `cancelled` | UI Kanban, y se auto-sincroniza desde el eje de aprobación |
| **Estado de aprobación** (circuito) | `estado_aprobacion` | Permiso para avanzar en el circuito | `pendiente_revision`, `aprobado_claude`, `aprobado_revisor`, `requiere_irving`, `aprobado_irving`, `rechazado`, `en_progreso`, `completado`, `cancelado` | Revisor, worker, MergeRunner, cron, Irving |
| **Nivel de riesgo** | `nivel_riesgo` | Cuánta autonomía tiene el circuito sobre él | `A`, `B`, `C` (o `NULL` = sin triajear) | Revisor / triaje (solo endurece A→B→C) |

Y una **cuarta** cosa que NO es una columna sino un cálculo: la **ubicación visual** en la Torre
(la "estación"), que el modelo deriva sobre la marcha (`getEstacionAttribute`): `intake`, `listo`,
`bandeja`, `terminal`, `integracion`, `done`.

> **Diferencia clave (pregunta #20):**
> - **Estado técnico** = casilla del Kanban. Ej.: un item con `status=in_progress` sale en la columna
>   "En progreso" de la pestaña *Hoja de ruta*.
> - **Estado de aprobación** = permiso del circuito. Ej.: `aprobado_irving` significa "Irving dio luz
>   verde para que el circuito lo trabaje", **no** que esté terminado.
> - **Ubicación visual** = dónde lo pinta la Torre. Un mismo item con `estado_aprobacion=requiere_irving`
>   aparece en la tarjeta **Tu Bandeja** aunque su `status` siga en `pending`.

⚠ **No existe** una columna llamada `estado`, ni `commit`, ni `rama`, ni `decision_irving`.
Lo que hay es: `status` + `estado_aprobacion` (los "estados"), `branch` + `merge_commit` (git),
y el archivo mal nombrado `..._add_decision_irving...` que en realidad crea `opciones`/`opcion_elegida`.

---

## 3. Diagrama de flujo REAL

Este es el circuito tal como está implementado (no el ideal). Las flechas de vuelta atrás son parte
del diseño real, no errores.

```
                          ┌──────────────────────────────────────────────┐
                          │  NACE UN ITEM                                 │
                          │  · UI "Agregar item" (POST /items)            │
                          │  · Sub-item "＋ Seguimiento"                  │
                          │  · Vía externa (Claude Cowork / MCP)          │
                          │  · Seeders/migraciones                        │
                          │  → status=pending, estado_aprobacion=         │
                          │    pendiente_revision, nivel_riesgo=NULL      │
                          └───────────────────────┬──────────────────────┘
                                                  │
                       ┌──────────────────────────▼───────────────────────┐
                       │  TRIAJE DE RIESGO  (nivel_riesgo NULL → A/B/C)    │
                       │  circuito:revisar-backlog / priorizar-seguridad   │
                       │  (Opus/Sonnet). Solo ENDURECE (nunca pone A).     │
                       └───┬───────────────┬───────────────────┬──────────┘
                           │ A             │ B                 │ C
                           ▼               ▼                   ▼
                  ┌────────────────┐ ┌───────────────┐ ┌─────────────────────┐
                  │ A auto-ejecutable│ REVISOR (#338)│ │ Brief para Irving   │
                  │ (o pendiente_   │ │ ¿seguro?      │ │ circuito:brief-c    │
                  │  revision + A)  │ │ sí→aprobado_  │ │ → requiere_irving   │
                  │                 │ │   revisor     │ │   (Tu Bandeja)      │
                  │                 │ │ no→requiere_  │ │                     │
                  │                 │ │   irving      │ │  Irving decide:     │
                  └───────┬─────────┘ └──┬──────────┬─┘ │  ✓ Aprobar →        │
                          │              │          │   │  aprobado_irving    │
                          │              │          └───┼──► (o rechaza)      │
                          │              │              └──────────┬──────────┘
                          ▼              ▼                         ▼
                  ┌──────────────────────────────────────────────────────────┐
                  │  COLA EJECUTABLE  (scopeAutoEjecutable)                   │
                  │  A aprobado_claude · A pendiente_revision ·              │
                  │  aprobado_revisor · aprobado_irving                       │
                  └───────────────────────────┬──────────────────────────────┘
                                              │  circuito:scheduler (cron 1 min)
                                              │  reclamo ATÓMICO
                                              ▼
                  ┌──────────────────────────────────────────────────────────┐
                  │  EN PROGRESO  (estado_aprobacion=en_progreso,            │
                  │  worker_sid=wt-K)  — el worker claude -p en su worktree   │
                  │  1. circuito:rama  → crea branch=circuito/item-<id>-slug  │
                  │  2. edita + commit selectivo                             │
                  │  3. VERIFICA (php -l / npm-build)                         │
                  │     falla → revierte + estado=requiere_irving ──────┐     │
                  │  4. circuito:integrar → ENCOLA merge                │     │
                  │     · nivel C sin --force → requiere_irving ────────┤     │
                  │  timeout 600s → requiere_irving (aprobado_por=      │     │
                  │  timeout, NO se re-encola)                          │     │
                  └───────────────────────────┬────────────────────────┼─────┘
                                              │ (A/B verificado)        │
                                              ▼                         │
                  ┌──────────────────────────────────────────────┐     │
                  │  INTEGRACIÓN (cola de merge en settings)      │     │
                  │  MergeRunner.drain() on-box (usuario meganet) │     │
                  │  · checkout main                              │     │
                  │  · git merge --no-ff --no-commit <branch>     │     │
                  │    CONFLICTO → merge --abort → requiere_irving├─────┤
                  │  · frontend-gate ON + toca UI → requiere_irving────┤
                  │  · regresión (php -l/boot) falla → requiere_irving─┤
                  │  · commit merge → merge_commit=<sha>          │     │
                  └───────────────────────────┬──────────────────┘     │
                                              │ merge OK               │
                                              ▼                         │
                  ┌──────────────────────────────────────────────┐     │
                  │  COMPLETADO  (estado_aprobacion=completado,   │     │
                  │  status=done, merge_commit=<sha>)             │     │
                  │  · backend puro → auto-ARCHIVADO              │     │
                  │  · toca UI → revision_ui=true (espera que     │     │
                  │    Irving lo mire; NO se archiva solo)        │     │
                  └───────────────────────────┬──────────────────┘     │
                                              ▼                         │
                  ┌──────────────────────────────────────────────┐     │
                  │  HISTORIAL (archivado_at != NULL)             │     │
                  └──────────────────────────────────────────────┘     │
                                                                        │
   ┌────────────────────────────────────────────────────────────────────┘
   ▼   BANDEJA DE IRVING  (requiere_irving)  ── EL SUMIDERO ──
   Aquí caen: escalados del worker, conflictos de merge, regresiones, frontend-gate,
   timeouts, reaper de colgados, revisor que no autoriza, y C sin decidir.
   ⚠ BUCLE: el scheduler VUELVE a reclamar aprobado_irving y destrabe(opus) re-aprueba
   → el worker re-escala → oscila sin fin si el item no puede auto-integrarse (ver §11).
```

---

## 4. Tabla de estados (`estado_aprobacion`)

| Estado | Significado real | ¿Terminal? | ¿Reclamable por el scheduler? | Ubicación visual |
|---|---|---|---|---|
| `pendiente_revision` | Recién nacido / sin triajear / reciclado | No | Solo si `nivel_riesgo=A` | intake / bandeja |
| `aprobado_claude` | Nivel A marcado seguro internamente (auto-corre) | No | **Sí** | Cola ejecutable |
| `aprobado_revisor` | B técnico seguro autorizado por el Revisor adversarial (#338) | No | **Sí** (si revisor ON) | Cola ejecutable |
| `aprobado_irving` | Irving dio luz verde con el botón **✓ Aprobar** | No | **Sí** | Cola ejecutable |
| `requiere_irving` | **El sumidero.** Necesita decisión/acción humana | No | No | **Tu Bandeja** |
| `en_progreso` | Un worker lo tiene tomado ahora mismo | No | No (ya tomado) | Terminales / En trabajo |
| `rechazado` | Irving lo descartó (**✕ Rechazar**) | Sí | No | — |
| `completado` | Mergeado a `main` y verificado | Sí | No | Integración / Historial |
| `cancelado` | Anulado (⊘ Cancelar / rechazo-borrar) | Sí | No | — |

Notas de sincronización automática (`RoadmapItem::booted`):
- Si `estado_aprobacion` pasa a `completado` → fuerza `status=done` + `completed_at`.
- Si el `status` avanza estando en `pendiente_revision`/`en_progreso`, espeja: done→`completado`,
  in_progress→`en_progreso`, cancelled→`cancelado`.

---

## 5. Tabla de transiciones (quién dispara qué)

| Estado actual | Acción | Nuevo estado | Quién lo ejecuta | Archivo/comando | Condición |
|---|---|---|---|---|---|
| (no existe) | Crear item | `pendiente_revision` | UI / Cowork / seeder | `RoadmapController::store`, `booted:creating` | — |
| `pendiente_revision` (nivel NULL) | Triaje | fija `nivel_riesgo` B/C (+`requiere_irving` si C) | Revisor (cron) | `circuito:revisar-backlog --apply` | nunca pone A |
| `pendiente_revision` (B) | Revisor autoriza | `aprobado_revisor` | Revisor adversarial | `RevisarBacklogCommand` / `RevisarItemCommand` | en alcance + revisor ON |
| `pendiente_revision` (B) | Revisor NO autoriza | `requiere_irving` | Revisor | idem | fuera de alcance / duda |
| cualquiera (C) | Armar brief | queda `requiere_irving` + `comentarios_claude` | Opus (cron) | `circuito:brief-c` | C sin brief |
| `requiere_irving`/`pendiente_revision` | **✓ Aprobar** (Irving) | `aprobado_irving` | Irving (UI) | `POST /circuito/decidir` (aprobar) | responder todas las preguntas |
| cualquiera | **✕ Rechazar** (Irving) | `rechazado` | Irving (UI) | `POST /circuito/decidir` (rechazar) | — |
| cualquiera | **✔ Cerrar** (Irving) | `completado`+`status=done` | Irving (UI) | `POST /circuito/decidir` (cerrar) | — |
| cualquiera | **⊘ Cancelar** (Irving) | `cancelado` | Irving (UI) | `POST /circuito/decidir` (cancelar) | — |
| `aprobado_*` / A `pendiente_revision` | Reclamo | `en_progreso` + `worker_sid` | Scheduler / worker | `circuito:scheduler`, `circuito:claim-next` | módulo-disjunto, no pausado |
| `en_progreso` | Worker termina y encola | (queda hasta merge) | Worker | `circuito:integrar` | A/B verificado |
| `en_progreso` (C) | Worker intenta integrar | `requiere_irving` | Worker | `IntegrarItemCommand:45-50` | C sin `--force` |
| `en_progreso` | Verificación falla | `requiere_irving` | Worker | `prompt-item.txt` paso 6b | php -l/build roto |
| `en_progreso` | Timeout 600s | `requiere_irving` (`aprobado_por=timeout`) | vuelta.sh | `vuelta.sh:143-146` | RC=124, no re-encola |
| `en_progreso` colgado | Reaper | `requiere_irving` | Reaper (cron) | `circuito:reap-stuck` | >25 min sin avance |
| cola de merge | Merge limpio | `completado`+`merge_commit` | MergeRunner (meganet) | `MergeRunner::markMerged` | sin conflicto/regresión |
| cola de merge | Conflicto/regresión/frontend | `requiere_irving` | MergeRunner | `MergeRunner:58-67, 250-273` | ver §10 |
| `requiere_irving` (técnico seguro) | Des-trabe | `aprobado_claude`/`aprobado_revisor` | Opus (cron) | `circuito:destrabe` | reejecutable + sin rebote previo |
| `completado` mergeado | **↩ Revertir** | `requiere_irving`, `merge_commit=NULL` | Irving (UI) | `POST /integracion/revert` | árbol limpio |
| `requiere_irving`/mergeado | **✕ Rechazar rama** reciclar | `pendiente_revision`, `branch=NULL` | Irving (UI) | `POST /integracion/rechazar` | comentario obligatorio |
| completado/mergeado | **Archivar** | `archivado_at` set | Irving / MergeRunner | `POST /integracion/archivar` | — |
| archivado | **↩ Traer al radar** | `archivado_at=NULL` | Irving (UI) | `POST /integracion/desarchivar` | — |

**Automáticas (sin humano):** triaje, revisor, brief-c, reclamo del scheduler, integrar, merge,
reaper, timeout-parking, destrabe, priorizar-seguridad.
**Requieren a Irving:** ✓ Aprobar, ✕ Rechazar, ✔ Cerrar, ⊘ Cancelar, ✓ Mergear a dev, ↩ Revertir,
Archivar. **Pueden regresar hacia atrás:** todo lo que cae en `requiere_irving` (el sumidero).

---

## 6. Mapa de secciones de la Torre

La Torre son **6 pestañas** (contenedor `ReleasesIndex.vue`, reactivo por `tab`, sin rutas). "Panorama",
"Tu Bandeja", "Pendientes", "En trabajo" **no son pestañas**: son tarjetas dentro de Panorama.

| Pestaña / bloque | Componente Vue | API que consume | Qué muestra (filtro real) | Qué excluye |
|---|---|---|---|---|
| **Panorama** | `TorreControl.vue` | `GET /api/roadmap/torre` + polling `/circuito/estado` (4 s) | Dashboard completo | — |
| · Cola ejecutable | ″ | `data.cola_ejecutable` = `scopeAutoEjecutable` | Lo que el circuito auto-corre | requiere_irving, rechazado, terminales, C sin aprobar |
| · **Tu Bandeja** | ″ | `data.cola_requiere_irving` = `scopeBandeja` | "Requiere tu decisión" | done/cancelled/in_progress, terminales, candado humano |
| · KPIs por estado | ″ | `resumen.por_estado` | Solo 4 de 9 estados | los otros 5 |
| · Ejecuciones | ″ | `data.ejecuciones` | Últimas 12 vueltas del cron | — |
| **Hoja de ruta** | `RoadmapTab.vue` | `GET /api/roadmap/items?vista=backlog` | Kanban de **intake** | ⚠ TODO lo triado/tomado/mergeado (ver D8) |
| **Terminales** | `TorreTerminales.vue` | `GET /circuito/estado` (polling 3 s) | Rejilla de workers vivos | sin sesiones activas → vacío honesto |
| **Integración** | `IntegracionRamas.vue` | `GET /integracion` (radar) + `/integracion/historial` | Ramas con `branch`, no archivadas | — |
| **Historial de versiones** | inline en `ReleasesIndex` | releases | Timeline de versiones (NO del circuito) | — |
| **Reporte** | `AuditReport.vue` | `GET /releases/audit/report` | Métricas + checklist | — |

**Refresco:** Panorama y Terminales hacen polling (`setInterval`); Integración y Hoja de ruta **no**
tienen polling continuo (solo botón `↻ Actualizar` / refetch tras cada acción). Integración solo
poll-ea 15×2.5 s justo después de pulsar *Mergear*.

---

## 7. Mapa de botones (frontend → endpoint → efecto en BD)

### Panorama · Tu Bandeja

| Botón | Método Vue | Endpoint | Qué hace REALMENTE en BD | Si falla |
|---|---|---|---|---|
| **✓ Aprobar** | `decidir(it,'aprobar')` | `POST /circuito/decidir` | Solo `estado_aprobacion=aprobado_irving` (+ log). **No ejecuta, no mergea.** | 422 si faltan preguntas por responder; item no cambia |
| **Elegir opción** (chips) | `elegirOpcion` | `POST /circuito/elegir-opcion` | Persiste `opcion_elegida` / `preguntas[].opcion_elegida`. **No cambia estado.** | Error **silencioso** (se traga) |
| **✕ Rechazar** | `decidir(it,'rechazar')` | idem decidir | `estado_aprobacion=rechazado` | aviso, conserva selección |
| **✔ Cerrar** | `decidir(it,'cerrar')` | idem | `completado` + `status=done` | idem |
| **⊘ Cancelar** | `decidir(it,'cancelar')` | idem | `cancelado` + `status=cancelled` | idem |
| **💬 Comentar** | `decidir(it,'comentar')` | idem | ⚠ **Sobrescribe** `comentarios_claude` (pisa la recomendación de Claude) | idem |
| **🔊 Escuchar** | `leer` (torreEscuchar.js) | — (voz del navegador) | Nada en BD; narra `title`+`reporte_coloquial` | — |
| **🔎 Ver más** | `verMas` | abre `enlace_revision` ‖ `/roadmap/item/{id}` | Nada; abre pestaña | — |
| **＋ Seguimiento** | `crearSeguimiento` | `POST /circuito/seguimiento` | Crea sub-item `pendiente_revision` con `origen_item_id`; opcional cierra el origen | — |
| **🔥 Urgente** | `marcarUrgente` | `POST /items/{id}/urgente` | `urgente=1`, `priority='alta'`. En bandeja: **solo reordena** (no dispara) | — |

### Integración

| Botón | Método Vue | Endpoint | Qué hace REALMENTE | Si falla |
|---|---|---|---|---|
| **✓ Mergear a dev** | `merge` | `POST /integracion/merge` | Solo **ENCOLA** el merge (`settings`). El merge real lo hace el runner on-box, asíncrono. "dev" = `main`. | Poll 15×2.5 s; si el runner escala → item a `requiere_irving` |
| **↩ Revertir** | `revert` | `POST /integracion/revert` | `git revert` directo; `merge_commit=NULL`, `estado=requiere_irving` | 409 si árbol sucio / conflicto |
| **✕ Rechazar** rama | `rechazar` | `POST /integracion/rechazar` | reciclar → `pendiente_revision`+`branch=NULL`; borrar → `cancelado`+archivado (**no** hard-delete) | comentario obligatorio |
| **🗂 Archivar lo mergeado** | `archivarMergeados` | `POST /integracion/archivar` | `archivado_at` masivo de mergeados | — |
| **Auto-merge ON/OFF** | `toggleAutoMerge` | `POST /integracion/modo` | `settings.circuito_modo_integracion` | — |
| **🏷 Marcar para versión** | `marcarVersion` | `POST /integracion/marcar-version` | toggle `marcado_version` | — |

### Panorama · barra superior

| Botón | Endpoint | Efecto |
|---|---|---|
| **▶ Ejecutar vuelta ahora** | `POST /circuito/disparar` | Pide vuelta inmediata (423 si pausado) |
| **⏸ Pausar / ▶ Reanudar** | `POST /circuito/toggle` | Kill switch `circuito_pausado` |

### Respuestas directas a las preguntas del brief

- **¿Por qué "Aprobar" no siempre hace merge?** Porque **✓ Aprobar** solo escribe
  `estado_aprobacion=aprobado_irving`. En ese momento el item **todavía no tiene rama**. Debe ser
  reclamado por un worker (→`en_progreso`), ejecutado (el worker crea `branch`), verificado y **recién
  entonces** encolado a merge. Además, si el modo es "revisar-y-mergear", el merge espera tu botón
  **✓ Mergear a dev**. **Aprobar la decisión ≠ aprobar el resultado.**
- **¿Por qué un item nivel C puede volver a `requiere_irving` tras aprobarlo?** Al aprobar un C, se
  vuelve reclamable; un worker lo toma y crea una rama, pero `circuito:integrar` **rebota los C a
  `requiere_irving` salvo `--force`** (`IntegrarItemCommand:45-50`). Y aunque llegara al merge, un
  conflicto, una regresión o el frontend-gate lo devuelven a tu bandeja.
- **¿Por qué algunos items con código terminado siguen en Tu Bandeja?** Porque el **código está en la
  rama pero `merge_commit` sigue NULL**. Para nivel C el circuito no puede cerrar el lazo: falta tu
  clic de **Mergear** (o `--force`). Ejemplos reales: #185 (15 aprobaciones, rama lista) y #212.
- **¿Por qué un conflicto de Git manda el item a la bandeja?** Porque el `MergeRunner` hace
  `git merge --abort` para **dejar `main` intacto** y escala el item a `requiere_irving` con el motivo
  en el `log` (`MergeRunner:115-121, 58-67`). No hay resolución automática de conflictos.

---

## 8. Niveles A / B / C (confirmado con código, no con la intención)

### Nivel A — "seguro: aditivo, reversible, no toca dinero/permisos/auth/producción"
- **Qué ejecuta automáticamente:** todo. Un A puede quedar `aprobado_claude` y ser reclamado y
  ejecutado por un worker **sin que Irving intervenga**.
- **Quién aprueba:** internamente (Claude Code/Irving). ⚠ Guard duro: la **vía externa** (Cowork/MCP)
  **no** puede auto-conceder A; si ella misma sube el nivel a A, el máximo alcanzable es
  `requiere_irving` (`RoadmapCircuitoService::guard`). El `nivel_riesgo_origen` debe ser `interno`.
- **Auto-integra:** sí. `circuito:integrar` encola su merge y el `MergeRunner` lo aplica solo.
- **Restricción:** el triaje **nunca degrada** un nivel (solo A→B→C). Un A mal clasificado no se
  "sube" a A desde fuera.

### Nivel B — "requiere confirmación de Irving en sesión"
- **Qué requiere:** confirmación humana… **pero** existe el **Revisor adversarial (#338)**: un agente
  (Sonnet rutina / Opus difícil) que puede **autorizar B técnicos seguros** → `aprobado_revisor`, para
  que el circuito no se frene en lo rutinario. Está **OFF por defecto** (flag `circuito_revisor`).
- **¿Una aprobación async basta?** Para B *rutinario* que el Revisor autorice, sí (se ejecuta solo).
  Para B que toque la **frontera dura** (dinero/prod/seguridad/negocio, denylist en `config/circuito.php`)
  **no**: se escala sin gastar IA.
- **Cuándo pide sesión en vivo:** cuando el trabajo no se puede hacer autónomo (cutover en producción,
  config de SO fuera del repo). Ejemplos reales atascados: #203 (cutover WhatsApp ventas en vivo),
  #186 (`/etc/asterisk/manager.conf`, fuera del repo).
- **Comportamiento actual del código:** con revisor OFF (default), **todo B espera a Irving** en la
  bandeja.

### Nivel C — "decisión de diseño exclusiva de Irving. Jamás sin su decisión"
- **Qué significa realmente:** el circuito **puede escribir el código** de un C aprobado, pero **NO
  puede integrarlo solo**. `circuito:integrar` rebota los C a `requiere_irving` salvo `--force`.
- **Por qué no auto-mergea:** por diseño de seguridad (`IntegrarItemCommand:45-50`).
- **Cuál es la acción final de Irving:** pulsar **✓ Mergear a dev** en Integración (que internamente
  encola con `by='boton'` → equivale a `--force`), o revertir/rechazar.
- **Diferencia entre "aprobar una opción" y "el botón de merge":** *Elegir opción* solo guarda
  `opcion_elegida` (qué camino tomar) y *✓ Aprobar* pone `aprobado_irving` (permiso de trabajar).
  **Ninguno mergea.** El merge es un acto separado en la pestaña **Integración**. Ej.: #185 tiene la
  opción elegida y 15 aprobaciones, pero como nadie pulsó *Mergear*, sigue sin `merge_commit`.

---

## 9. Agentes del circuito (quién lee, escribe, decide, y qué NO puede hacer)

> **OpenAI NO está en el circuito.** El único actor externo es **Claude Cowork** (vía token, corre
> fuera de la red) + un conector **MCP** (también Claude). Todos los agentes son Claude en distintos
> roles/modelos.

| Agente | Qué es | Lee | Escribe | Decide | NO puede | Evidencia que deja |
|---|---|---|---|---|---|---|
| **Ejecutor / Worker (wt-1..wt-6)** | `claude -p` (Sonnet) en su worktree, lanzado por `vuelta.sh` | el item completo, el repo | código en su rama; `branch`, `estado_aprobacion` (a completado/requiere_irving), `reporte_coloquial`, `enlace_revision` | si un item es seguro/ejecutable o hay que escalar | tocar prod, push, `add -A`, `migrate:fresh`, otros items, kill switch/flag revisor | commits en la rama, `CIRCUITO_META`/`CIRCUITO_FASE` en el log, `circuito_ejecuciones` |
| **Revisor (#338)** | agente adversarial (Sonnet rutina / **Opus** difícil) | item + perfil de decisiones de Irving | `estado_aprobacion` (aprobado_revisor/requiere_irving), `nivel_riesgo` (triaje) | si un B es "técnico seguro" o escala | conceder `aprobado_irving` (solo Torre); degradar nivel | tabla `circuito_revisiones`, sellos en `comentarios_claude` |
| **Opus (destrabe / brief-c / priorizar-seg)** | pasadas Opus por cron | bandeja `requiere_irving`, backlog | re-aprueba técnicos al pool; escribe briefs; tags de seguridad/dinero | categoría de escalado, prioridad | auto-ejecutar dinero/seguridad/negocio/prod | briefs y tags en `comentarios_claude` |
| **SEG-TRIAGE** | `circuito:priorizar-seguridad` (Opus) | backlog con señales regex | `priority=alta`, `requiere_irving`, tags `[SEG-TOP]`/`[DINERO-TOP]`/`[BLOCKED-NEGOCIO]`/`[PARKED-PROD]` | qué es riesgo real vs ruido | auto-aprobar nada sensible | sello `⟪SEG-TRIAGE⟫` |
| **Scheduler** | `circuito:scheduler` (cron, meganet) | items ejecutables, slots libres | `en_progreso`+`worker_sid` (reclamo atómico) | qué item va a qué worker | tocar git de items; correr en pausa | `settings.circuito_scheduler_beat` |
| **Merge-runner** | `MergeRunner::drain` (on-box, meganet) | cola de merge, ramas | merge a `main`, `merge_commit`, `completado`/`requiere_irving`, archivado | si el merge es limpio/seguro | push, prod, resolver conflictos (aborta) | commit `Integra circuito #N`, `log` del item, canal `roadmap_externo` |
| **Frontend-gate** | check dentro del MergeRunner (flag `circuito_frontend_gate`) | diff staged | `holdForReview` → `requiere_irving`+`revision_ui` | si el merge toca UI y debe verlo Irving | mergear UI sin revisión (si ON) | `aprobado_por='merge-runner(frontend-gate)'` |
| **Watchdog / Supervisor** | `circuito:watchdog` (cron), `SupervisorService` | salud de scheduler+workers, anti-ping-pong | relanza workers, escala anomalías | recuperar o escalar | — | stdout con ↻/⚠, banner de colisión |
| **Reaper** | `circuito:reap-stuck` (cron) | `en_progreso` viejos (>25 min) | `requiere_irving`+nota `[reaper]` | qué worker murió | tocar items de humanos | sello en `comentarios_claude` |
| **Cowork / MCP (externo)** | Claude fuera de la red, por token | items (lectura) | `estado_aprobacion`, `nivel_riesgo` (solo endurecer), `comentarios_claude` | proponer triaje | conceder aprobado_irving/revisor; degradar nivel; llevar a A auto | audit log, `nivel_riesgo_origen=externo` |
| **Irving (humano)** | tú, en la Torre | todo | vía botones: aprobar/rechazar/cerrar/cancelar/mergear/revertir/archivar | **todo lo de la frontera dura** | — | `aprobado_por='irving:<login>'`, `log` |

---

## 10. Flujo Git en detalle

1. **Rama:** `circuito:rama <id>` crea `circuito/item-<id>-<slug>` (slug = 40 chars del título) **desde
   el tip de `main` sin checar main** (`git checkout -b <branch> main`). Ej.: `circuito/item-117-...`.
2. **Worktrees:** viven en `/home/meganet/circuito/wt-1 … wt-6` (+`wt-exec`). Se aprovisionan idempotentes
   con `git worktree add --detach`, **copiando** `vendor` (no symlink) y symlinkeando `.env`/`node_modules`.
3. **Commit del trabajo:** lo hace el worker `claude -p` dentro de su worktree (add selectivo, un commit
   por sub-paso, en español). **No se guarda el SHA del trabajo en el item** (no hay columna `commit`);
   solo queda en la rama.
4. **Llega a Integración:** `circuito:integrar` **encola** (no mergea). Motivo: la Torre corre como
   `www-data` y no puede escribir `.git`.
5. **"Mergear a dev" = merge a `main`** (⚠ no hay rama `dev`; "dev" es alias cosmético): el `MergeRunner`
   hace `git checkout main` + `git merge --no-ff --no-commit <branch>`, commit
   `"Integra circuito #<id> (<branch>) a main"`.
6. **Merge limpio:** commit de merge → `merge_commit=<sha>`, `status=done`, `estado_aprobacion=completado`.
7. **Conflicto:** `git merge --abort` → **`main` intacto** → item a `requiere_irving` con motivo en el log.
8. **Regresión** (php -l / boot falla) o **frontend-gate**: igual → abort → `requiere_irving`.
9. **"main intacto":** significa que ante cualquier fallo el runner deshace el merge a medias; `main`
   nunca queda roto ni a medio integrar.
10. **`merge_commit`** solo lo escribe `MergeRunner::markMerged` (SHA de `git rev-parse HEAD`).
11. **La rama NUNCA se borra** (no hay `git branch -d` en todo el módulo). Quedan colgando decenas.
12. **Archivado:** backend puro → auto-archivado; UI-verificable → `revision_ui=true`, espera que
    Irving lo mire.

⚠ **No hay rebase, ni merge de `main`→rama, ni estrategia `ours/theirs`.** La única "frescura" es que la
rama nace del tip de main y el worktree se re-sincroniza cada vuelta. **Consecuencia real:** ramas de
vida larga quedan muy atrás de main (medido: #212 va **338 commits detrás**), y el conflicto solo se
descubre al intentar el merge (que aborta y escala). Eso es **ruido de divergencia**: la rama no está
rota, simplemente envejeció.

- **rebase:** reordenar los commits de una rama sobre la punta de otra. Ej.: *no se usa* — por eso las
  ramas viejas divergen.
- **merge de main→rama:** traer lo nuevo de main a la rama antes de integrar. Ej.: *no se hace* → #212.
- **conflicto semántico:** el merge aplica limpio pero el código se contradice (dos items tocan lo mismo
  con criterios opuestos). Ej.: el protocolo anti-ping-pong del `prompt-item.txt` existe justo para esto.
- **ours vs theirs:** en un conflicto, quedarte con tu versión (*ours*) o la del otro (*theirs*). Ej.:
  *no aplica* — el runner no resuelve, aborta.

---

## 11. Causas de los bucles (evidencia real: BD + logs)

**Mecánica del loop:** el `SchedulerCommand` reclama como "ejecutable" cualquier item en `aprobado_irving`.
Pero para un nivel C, o para dinero/prod, ejecutar/mergear es **imposible sin acción humana**. El worker
lo re-verifica y lo devuelve a `requiere_irving`; luego **`destrabe` (Opus)** lo re-aprueba al pool o
**Irving vuelve a pulsar Aprobar**, y vuelve a empezar. `merge_commit` nunca se llena → oscila sin fin.

| # | Nivel | Estado hoy | Rama | Commits | Opción | merge_commit | Causa raíz |
|---|---|---|---|---|---|---|---|
| 117 | C | requiere_irving | sí | 2 | ✅ | NULL | C no auto-integra + seguridad fiscal (CFDI/PAC) |
| 185 | C | aprobado_irving | sí | 2 | ✅ | NULL | **Solo falta el merge manual** (15 aprobaciones) |
| 186 | B | requiere_irving | **no** | — | ❌ | NULL | fuera del repo (config SO) + requiere sesión |
| 197 | B | **completado** ✔ | sí | merged | ❌ | **717e6a4f** | RESUELTO tras 7 rebotes (su merge sí llegó) |
| 203 | B | requiere_irving | **no** | — | ❌ | NULL | requiere sesión en vivo (cutover WhatsApp) |
| 212 | C | requiere_irving | sí | 1 | ❌ | NULL | **Solo falta el merge manual** (338 commits detrás) |
| 223 | B | aprobado_irving | sí (**vacía**) | **0** | ✅ | NULL | frontera dura dinero (doble cobro OpenPay): worker creó la rama pero se rehusó a commitear |

**Qué SÍ cambia en cada vuelta:** `estado_aprobacion` (oscila), `worker_sid`, `revisado_at`,
`aprobado_por`, `comentarios_claude` (crece), `updated_at`.
**Qué NO cambia:** `merge_commit` (sigue NULL), `branch`, `opcion_elegida`, el código.

### Agrupación por causa

1. **C nunca auto-integra + solo falta merge manual → #117, #185, #212.** El trabajo está hecho y
   commiteado; el circuito solo puede dejarlo en la bandeja. **No es un bug: falta tu clic de Mergear.**
2. **Frontera dura dinero/prod, aprobación async insuficiente → #223, #197 (antes).** El worker exige
   confirmación en vivo para dinero; la aprobación por clic no basta. La rama de #223 quedó **vacía**.
3. **Requiere sesión en vivo / fuera del repo → #186, #203.** No hay código autónomo posible.
4. **Aprobación sin `opcion_elegida` → #186, #203, #212, #197.** El worker no sabe qué camino tomar.
5. **Ping-pong `destrabe(opus)`↔ejecutor → #186, #203, #212.** El anti-loop `rebotesDelEjecutor` los
   frena tras N ciclos, pero cada re-aprobación previa ya quemó una vuelta de cómputo.

**Causa raíz última:** el scheduler trata `aprobado_irving` como *reclamable-para-ejecución*, pero para
C y dinero/prod la única salida es una acción humana (merge/`--force`/sesión). Sin un estado
"aprobado-pero-esperando-merge-humano" separado, el item oscila indefinidamente.

---

## 12. Guía práctica para Irving — "Qué debo hacer yo en cada caso"

*(Esta sección está pensada para leerse sin saber de código. Cada término trae un ejemplo de la Torre.)*

### Imagina la Torre como una FÁBRICA

- **Hoja de Ruta** = el **almacén de pedidos**. Todo lo que alguien pidió construir. Ej.: pestaña
  *Hoja de ruta*, botón *Agregar item*.
- **Triaje de riesgo** = el **inspector de la entrada** que le pone a cada pedido una etiqueta de color:
  **A** (verde, seguro), **B** (amarillo, confírmalo), **C** (rojo, decisión tuya).
- **Cola ejecutable** = la **banda transportadora**: los pedidos con permiso para entrar a taller.
- **Workers (wt-1..wt-6)** = los **técnicos**, cada uno en su **banco de trabajo aislado** (worktree)
  para no estorbarse. Ej.: la pestaña *Terminales* te muestra qué técnico está en qué pedido.
- **En progreso** = un técnico trabajando **ahora**. Ej.: el visor "Trabajando ahora" en Panorama.
- **Integración** = el **control de calidad y el ensamblaje final** a la línea principal (`main`).
  Ej.: pestaña *Integración*, botón *✓ Mergear a dev*.
- **Tu Bandeja** = la **mesa del dueño**: solo lo que de verdad necesita tu firma. Ej.: tarjeta
  *Tu bandeja* en Panorama.
- **Historial** = el **archivo** de pedidos terminados y guardados.

> ⚠ **Lo más importante que debes saber (UI vs realidad):**
> - **✓ Aprobar** = "doy permiso para que lo trabajen". **NO** lo termina ni lo publica. La UI hace que
>   parezca "hecho", pero por dentro solo cambió un permiso.
> - **✓ Mergear a dev** = publicar a la línea principal. La palabra "dev" en pantalla en realidad es
>   `main`, y el botón **no publica al instante**: pone el pedido en cola y un obrero lo publica en
>   segundos. Si algo choca, el pedido **regresa solo a tu bandeja**.
> - El **semáforo verde "✓ Verificado"** de Integración **no** prueba que se corrieron pruebas: solo
>   significa que el pedido ya tiene código listo. No lo leas como garantía.

### Caso: "Requiere tu decisión" (item en Tu Bandeja)
- **Qué revisar:** el `reporte_coloquial` (pulsa *🔊 Escuchar*) y las opciones/preguntas de la tarjeta.
- **Cuándo elegir opción:** si la tarjeta ofrece caminos (chips), marca uno **antes** de aprobar. Sin
  responder todas las preguntas, *✓ Aprobar* te dará error y no hará nada.
- **Cuándo NO volver a aprobar:** si ya lo aprobaste y sigue rebotando, **aprobar otra vez no sirve** —
  lo estás re-alimentando al bucle. Revisa si lo que falta es el **merge** (ver siguiente caso).
- **Cuándo usar Mergear:** cuando el pedido ya tiene rama con código (típico de nivel **C**) y solo
  falta publicarlo. Ej.: #185 y #212 llevan semanas esperando **exactamente** tu clic de *Mergear*.
- **Cuándo programar sesión:** cuando el pedido toca algo en vivo o fuera del código (un servidor, un
  cutover con clientes reales). Ej.: #203 (bot de ventas WhatsApp), #186 (config del conmutador).
- **Cuándo rechazar:** si ya no lo quieres, *✕ Rechazar* (lo descarta) o *⊘ Cancelar*.

### Caso: "Sin mergear" (código listo, no publicado)
El técnico ya construyó la pieza (hay rama con commits) pero `merge_commit` está vacío. **Acción:** ve a
*Integración*, busca la rama del item y pulsa **✓ Mergear a dev**. Si es nivel C, ese botón es la única
vía (equivale al `--force` interno).

### Caso: "Conflicto"
Dos piezas chocan al ensamblar. El sistema **no rompe la línea principal** (`main` queda intacto) y te
devuelve el pedido a la bandeja. **Acción:** normalmente conviene **✕ Rechazar → reciclar** (lo manda de
vuelta al almacén para rehacerlo desde main fresco), porque la rama probablemente está vieja/divergida.

### Caso: "Rama no existe"
El pedido está aprobado pero **nunca se le construyó nada** (rama vacía o sin rama). Ej.: #223 tiene la
rama creada pero **cero commits** porque el técnico se rehusó (toca dinero real). **Acción:** esto NO se
arregla aprobando; requiere una **sesión supervisada** contigo, o rechazarlo si ya no aplica.

### Caso: "Solo falta merge"
Idéntico a "Sin mergear": el trabajo está, falta tu clic de **Mergear**. Es el caso más común del bucle
(#117, #185, #212). **No lo apruebes de nuevo; mergéalo.**

### Caso: "Requiere sesión supervisada"
El pedido toca producción, dinero, un servidor externo, o exige verte decidir en vivo. El circuito
**nunca** lo hará solo (frontera dura). **Acción:** agéndate un momento para hacerlo con una sesión de
Claude Code interactiva, o recházalo.

### Caso: "Item padre" (con sub-items de seguimiento)
Un pedido puede tener hijos (`origen_item_id`) creados con *＋ Seguimiento*. **Acción:** decide y cierra
primero los hijos; cerrar el padre no cierra a los hijos automáticamente.

---

## 13. Glosario (cada término con ejemplo de la Torre)

- **Item / RoadmapItem:** un pedido de trabajo. Ej.: la fila #185 "Facturación CFDI".
- **`status` (estado técnico):** casilla del Kanban (`pending/in_progress/done/cancelled`). Ej.: la
  columna donde aparece en *Hoja de ruta*.
- **`estado_aprobacion`:** permiso del circuito. Ej.: `aprobado_irving` = "puede trabajarse".
- **`nivel_riesgo` (A/B/C):** cuánta autonomía tiene el circuito. Ej.: la barra "Items por nivel" en
  Panorama.
- **Cola ejecutable:** items con permiso de entrar a taller. Ej.: la lista del mismo nombre en Panorama.
- **Tu Bandeja:** lo que requiere tu decisión. Ej.: la tarjeta "Tu bandeja".
- **Worker / ejecutor (wt-K):** una sesión de Claude Code que construye. Ej.: "wt-3 = Sofía" en
  *Terminales*.
- **Worktree:** el banco de trabajo aislado de cada worker (una copia del proyecto para no pisarse). Ej.:
  `/home/meganet/circuito/wt-3`.
- **Rama (`branch`):** la línea donde el worker guarda su trabajo antes de publicarlo. Ej.:
  `circuito/item-185-facturacion`.
- **Merge / "Mergear a dev":** publicar la rama a la línea principal `main`. Ej.: botón *✓ Mergear a dev*.
- **`merge_commit`:** la huella de que sí se publicó. Ej.: #197 tiene `717e6a4f`; #185 lo tiene vacío.
- **Revisor (#338):** agente que autoriza B rutinarios para no molestarte. Ej.: estado `aprobado_revisor`.
- **Des-trabe / brief-c (Opus):** pasadas automáticas que re-clasifican o explican tu bandeja. Ej.: los
  textos "BRIEF DE DECISIÓN" en un item.
- **Frontera dura:** dinero/permisos/seguridad/producción/negocio: el circuito **nunca** los toca solo.
  Ej.: #223 (doble cobro) quedó parado por esto.
- **Kill switch (pausa):** el botón *⏸ Pausar* que congela a los workers (pero **no** los merges ya
  decididos).
- **`requiere_irving`:** el sumidero: todo lo que necesita tu mano cae aquí.
- **Reaper / timeout:** red de seguridad que libera items colgados >25 min o que tardaron >10 min.

---

## ② PROBLEMAS DETECTADOS

> Esta sección es diagnóstico, **no** propuesta ni cambio de código.

### Discrepancias UI ↔ backend (lo que la pantalla aparenta vs lo que hace)
1. **D1 — "✓ Aprobar" aparenta completar; solo cambia un flag** (`aprobado_irving`). No ejecuta.
2. **D2 — "Mergear a dev" / "Auto-merge a dev" apuntan a `main`, no a una rama `dev`.** "dev" es
   cosmético.
3. **D3 — El semáforo "✓ Verificado (regresión cero)" no prueba verificación:** solo mira si hay
   `merge_commit` o estado aprobado. Afirma más de lo que comprueba.
4. **D4 — "Mergear" es asíncrono** (encola); el botón implica acción inmediata.
5. **D5 — "Lanzar a Claude Code" no lanza nada:** copia el prompt al portapapeles.
6. **D6 — El badge "requiere tu decisión" es fijo:** se pinta aunque el item real esté en
   `pendiente_revision`/`aprobado_claude`, por lo que **la lista de Tu Bandeja puede tener más items que
   el KPI "Requiere Irving"** (desincronía visible).
7. **D7 — En "Cola ejecutable" se muestra el valor crudo de BD** (`aprobado_irving`) sin traducir.
8. **D8 — Los KPIs de "Hoja de ruta" solo miden el intake:** "En progreso" será casi siempre 0 aunque
   haya trabajo activo; toda la maquinaria "una sola tarea en progreso" opera sobre datos que nunca
   contienen `in_progress` → efectivamente inerte.
9. **D9 — "💬 Comentar" sobrescribe** `comentarios_claude`, pisando la recomendación de Claude.
10. **D10 — "🔥 Decisión urgente" también sube `priority='alta'`** silenciosamente.
11. **D11 — "Rechazar → BORRAR" no borra la fila** (marca `cancelado`+archivado). Mitigado por el texto.
12. **D12 — Fallos silenciosos:** *Elegir opción* y *cambiar voz* se tragan el error del POST; la UI
    muestra un estado que puede no haberse guardado.

### Problemas estructurales
- **P-BUCLE:** el scheduler reclama `aprobado_irving` como ejecutable aunque para C/dinero/prod la
  integración sea imposible sin un humano → oscilación infinita (§11).
- **P-DRIFT:** `reporte_tecnico` y `reporte_coloquial` están en el modelo pero **ninguna migración los
  crea** (drift de esquema; existen en la BD real por vía manual).
- **P-CRON-MUERTO:** `circuito:disparo-check` sigue en cron cada minuto pero es un **NO-OP deprecado**.
- **P-RAMAS:** las ramas nunca se borran (decenas colgando) y nunca se re-basan → divergencia (#212 va
  338 commits detrás) → conflictos tardíos que escalan.
- **P-NOMBRES:** el archivo `..._add_decision_irving...` no crea `decision_irving` (crea
  `opciones`/`opcion_elegida`): nombre engañoso.
- **P-CROSS-META:** en timeouts, el parser de `CIRCUITO_META` podía atribuir el resultado al item
  anterior del pool (mal-atribución #224→#203; mitigada en `vuelta.sh:143-146`).

---

## ③ CÓMO DEBERÍA MEJORAR (solo recomendaciones, sin tocar código)

### TOP 10 problemas del circuito actual
1. El scheduler re-reclama `aprobado_irving` que no puede integrarse → **bucle sistémico** (C/dinero/prod).
2. No hay estado "aprobado-esperando-merge-humano" separado de `aprobado_irving`.
3. La UI dice "Aprobar/Mergear/Verificado/dev" con semántica que no coincide con el backend (D1–D4).
4. KPIs de "Hoja de ruta" miden solo intake → cifras engañosas y maquinaria inerte (D8).
5. Desincronía Tu Bandeja vs KPI "Requiere Irving" (D6).
6. Ramas sin borrar ni re-basar → divergencia y conflictos tardíos.
7. `destrabe(opus)` re-aprueba items que el worker ya rebotó → quema cómputo (Max) en el ping-pong.
8. Drift de esquema (`reporte_*` sin migración) → frágil ante un `migrate:fresh` en otro entorno.
9. Errores silenciosos en *Elegir opción*/voz → el dueño cree que guardó y no.
10. Cron muerto (`disparo-check`) y archivo de migración mal nombrado → deuda de mantenimiento.

### TOP 10 mejoras recomendadas
1. **Nuevo estado `esperando_merge_irving`** (o excluir `aprobado_irving` nivel C del scope del
   scheduler) para cortar el bucle de raíz.
2. **Re-etiquetar la UI** para que el label diga lo que hace: "Aprobar → autorizar trabajo", "Mergear →
   publicar a main (asíncrono)", quitar "dev" o documentarlo.
3. **Semáforo honesto:** que "Verificado" refleje evidencia real de `php -l`/build, o renombrarlo a
   "Con código listo".
4. **KPIs de Hoja de ruta sobre el universo completo**, no solo intake; o etiquetar la pestaña como
   "Bandeja de entrada".
5. **Unificar Tu Bandeja y el KPI** para que cuenten el mismo conjunto.
6. **Higiene de ramas:** borrar ramas ya mergeadas y re-basar (o merge main→rama) las de vida larga
   antes de integrar.
7. **Anti-ping-pong más firme:** que `destrabe` no re-apruebe items ya rebotados por el ejecutor.
8. **Regularizar el esquema:** migración que cree formalmente `reporte_tecnico`/`reporte_coloquial`.
9. **No tragar errores** de *Elegir opción*/voz: mostrar aviso al dueño.
10. **Limpieza:** retirar la línea de cron `disparo-check` y renombrar la migración `decision_irving`.

---

## VERIFICACIÓN — qué se revisó

**Modelos:** `RoadmapItem`, `CircuitoEjecucion`, `RoadmapItemMemory`.
**Migraciones (roadmap_items y auxiliares):** create + `2026_07_08_210000` (circuito), `_010000`
(opciones/opcion_elegida, mal llamada decision_irving), `_020000` (branch/merge_commit), `_040000`
(cancelado/origen_item_id), `_050000` (marcado_version), `_060100` (urgente), `2026_07_11_120000`
(en_desarrollo_humano), `_130000` (revisor/aprobado_revisor + circuito_revisiones), `_220000` (ui
lifecycle), `_233000` (worker_sid), `2026_07_12_070000` (nivel_riesgo_origen), `_13_140000` (preguntas),
`_13_150000` (enlace_revision), `_030000` (circuito_ejecuciones), `_060200` (circuito_disparos),
permisos Spatie, y reconciliaciones (`_13_120000`, `_13_160000`).
**Controladores:** `RoadmapController`, `RoadmapMcpController`, `RoadmapExternalController`,
`RoadmapMemoryController`.
**Servicios:** `RoadmapCircuitoService`, `MergeRunner`, `RevisorService`, `SupervisorService`,
`WatchdogService`, `SessionTreeService`, `PerfilAprendizajeService`.
**Comandos Artisan (21):** `scheduler`, `disparo-check` (NO-OP), `claim-next`, `reap-stuck`, `watchdog`,
`revisar-backlog`, `revisar`, `destrabe`, `brief-c`, `priorizar-seguridad`, `proponer-opciones`, `rama`,
`integrar`, `merge-run`, `provision-worktree`, `vivo`, `registrar-ejecucion`, `flags`, `revisor`,
`backfill-reporte-coloquial`, `clasificar-items`.
**Scripts:** `deploy/circuito/{cron-wrap,vuelta,npm-build}.sh`, `prompt.txt`, `prompt-item.txt`,
crontab (`crontab.bak.PREMERGE_20260714_232421`), `config/circuito.php`, `config/roadmap_externo.php`,
`config/mcp_roadmap.php`.
**Componentes Vue:** `ReleasesIndex`, `TorreControl`, `IntegracionRamas`, `RoadmapTab`, `TorreTerminales`,
`TorreTrabajandoAhora`, `torreEscuchar.js`, `AuditReport`, `RoadmapItemDetalle`.
**Endpoints:** `api/roadmap/{torre,circuito/*,integracion/*,items/*,roadmap/item/*}`.
**Tablas:** `roadmap_items`, `circuito_ejecuciones`, `roadmap_item_memory`, `circuito_revisiones`,
`circuito_disparos`, `settings`.
**Estados encontrados:** `status`(4) + `estado_aprobacion`(9) + `nivel_riesgo`(A/B/C/NULL) +
`estacion`(6, virtual).
**Datos/logs consultados (solo lectura):** `roadmap_items` (items #117,185,186,197,203,212,223),
`circuito_ejecuciones`, `/home/meganet/circuito/logs/vuelta-*.log`, `git worktree/branch/log/rev-list`.

*Auditoría read-only. No se modificó código, BD ni git. No se ejecutaron workers ni merges.*

---

# GUÍA RÁPIDA PARA IRVING

*Lenguaje simple. Sin tecnicismos. Cada respuesta va al grano.*

### 1. Cuando un item aparece en Tu Bandeja, ¿qué debo revisar primero?
El **reporte en llano** (pulsa **🔊 Escuchar**): te dice qué se pide y por qué llegó a tu mesa. Luego
mira si la tarjeta trae **opciones/preguntas** (chips). Eso te dice si el circuito espera que **elijas
un camino** o solo que **des el visto bueno**.

### 2. ¿Cuándo debo presionar Aprobar?
Cuando estás de acuerdo en que **se trabaje** y el item **aún no tiene código hecho**. *Aprobar* = "sí,
constrúyanlo". No lo publica; solo da permiso. Si ves que ya hay una rama con trabajo, **no es momento de
Aprobar sino de Mergear** (pregunta 4 y 5).

### 3. ¿Cuándo debo elegir una opción antes de aprobar?
Siempre que la tarjeta muestre **caminos alternativos** (por ejemplo, "Opción 1: Facturama / Opción 2:
otro proveedor"). Marca uno **antes** de pulsar Aprobar. Si no respondes todas las preguntas, *Aprobar*
te dará error y no hará nada.

### 4. ¿Cuándo NO debo volver a aprobar porque solo falta merge?
Si el item **ya tiene código listo en su rama** y sigue en tu bandeja, **aprobar otra vez no sirve** — lo
único que consigues es re-alimentar el bucle. La pista: dice cosas como "ya implementado, esperando merge
manual". Ejemplos reales atascados así: **#185 (15 aprobaciones)** y **#212**. Lo que falta es tu clic de
**Mergear**, no otra aprobación.

### 5. ¿Cuándo debo ir a Integración?
Cuando un item **ya tiene su código construido** y falta publicarlo a la línea principal. La pestaña
**Integración** es el único lugar con el botón **✓ Mergear a dev**. Para items **nivel C**, ese botón es
obligatorio (el circuito nunca los publica solo).

### 6. ¿Qué significa realmente "Mergear a dev"?
Publicar el trabajo de la rama a la **línea principal del proyecto**. ⚠ Dos aclaraciones importantes:
- La palabra **"dev" en pantalla es en realidad `main`** (la línea de verdad). Es solo un nombre bonito.
- El botón **no publica al instante**: pone el trabajo **en cola** y un obrero lo publica en segundos. Si
  algo **choca**, el item **regresa solo a tu bandeja** y la línea principal queda **intacta**.

### 7. ¿Cómo sé si el item ya tiene código?
Aparece en la pestaña **Integración** con una **rama** (algo como `circuito/item-185-...`) y un **diff**
(los cambios). Si está en Integración, hay código construido esperando.

### 8. ¿Cómo sé si la rama está vacía?
En Integración, una rama vacía **no muestra cambios/diff** (0 líneas). Significa que se creó la rama pero
**el técnico no construyó nada** (normalmente porque se rehusó: toca dinero o algo peligroso). Ejemplo
real: **#223** (doble cobro OpenPay) tiene rama pero **cero commits**. Aprobarlo no lo arregla.

### 9. ¿Qué hago si aparece conflicto?
El sistema ya protegió la línea principal (no se rompió nada). Lo más práctico es **✕ Rechazar →
reciclar**: eso manda el item de vuelta al almacén para que se rehaga **desde cero y actualizado**,
porque la rama vieja probablemente ya divergió demasiado.

### 10. ¿Qué hago si requiere sesión supervisada?
Es un item que toca **producción, dinero, un servidor externo o un cambio en vivo con clientes reales**.
El circuito **nunca lo hará solo**. **Agéndate un momento** para hacerlo tú con una sesión interactiva de
Claude Code, o recházalo si ya no aplica. Ejemplos: **#203** (bot de ventas WhatsApp en vivo), **#186**
(configuración del conmutador telefónico).

### 11. ¿Qué items puede resolver el circuito SOLO?
Los **nivel A** (verdes: cambios seguros, aditivos, reversibles, que no tocan dinero/permisos/producción)
y los **nivel B rutinarios** que el Revisor autorice (si está encendido). Esos se construyen, verifican y
publican **sin ti**.

### 12. ¿Qué items SIEMPRE requieren mi intervención?
- Todos los **nivel C** (decisiones de diseño): el circuito construye pero **tú publicas**.
- Todo lo de la **frontera dura**: dinero, cobros, permisos/seguridad, producción/despliegue, negocio.
- Cualquier item que exija **sesión en vivo** o toque algo **fuera del código** (servidores, config del SO).

### 13. ¿Cómo sé que un item terminó y fue archivado?
Terminó cuando su estado es **Completado** y tiene **huella de publicación** (`merge_commit`, ej. #197 con
`717e6a4f`). Los cambios de **backend puro se archivan solos**; los que **tocan la interfaz** se quedan
visibles esperando que **tú los mires** (marca "quiero verlo"). El **Historial** (dentro de Integración)
lista lo archivado.

### 14. ¿Qué señales indican que un item entró en un BUCLE?
- Lleva **muchas aprobaciones/escalaciones** (el mismo item vuelve una y otra vez a tu bandeja).
- Sus **comentarios crecen** con notas repetidas ("N-ª escalación", "esperando merge manual").
- **Nunca obtiene `merge_commit`** (nunca se publica).
- Es **nivel C** o toca **dinero/prod**, y aún así el circuito lo sigue "tomando".
Si ves esto: **no lo apruebes de nuevo**. O lo **mergeas** (si tiene código) o lo pasas a **sesión** o lo
**rechazas**.

### 15. ¿Qué botones de la Torre hoy pueden resultar ENGAÑOSOS?
- **✓ Aprobar** → parece "terminado", pero **solo da permiso** para que lo trabajen.
- **✓ Mergear a "dev"** → dice "dev" pero publica a **`main`**, y **no es instantáneo** (encola).
- **Semáforo verde "✓ Verificado"** → **no prueba** que se corrieron pruebas; solo que hay código listo.
- **"Lanzar a Claude Code"** (en Hoja de ruta) → **no lanza nada**; solo copia el texto al portapapeles.
- **KPI "En progreso"** de Hoja de ruta → casi siempre **marca 0** aunque haya trabajo activo (mide solo
  la bandeja de entrada, no todo).
- **"Rechazar → BORRAR"** → dice "BORRAR" pero **conserva la fila** (solo la cancela y archiva).
- **💬 Comentar** → **pisa** el comentario anterior de Claude en lugar de agregarse.

---

## Diagrama de una página — el viaje de un item

```
                              💡 IDEA
                                │
                                ▼
                          📋 HOJA DE RUTA
                                │
                                ▼
                     🎨 CLASIFICACIÓN A / B / C
                                │
                                ▼
                          🛠️  WORKER (wt-K)
                                │
                                ▼
                       🌿 RAMA Y COMMIT
                                │
                                ▼
                        🔍 INTEGRACIÓN
                                │
                                ▼
                        ⬆️  MERGE A MAIN
                                │
                                ▼
                          📦 ARCHIVO


RUTAS ALTERNAS (cuando el camino recto se interrumpe)
─────────────────────────────────────────────────────────────────
  REQUIERE DECISIÓN ─────────►  🗂️  TU BANDEJA
                                    (tú apruebas / eliges / rechazas)

  REQUIERE SESIÓN ───────────►  🧑‍💻 SESIÓN CON IRVING
                                    (dinero, prod, cambio en vivo)

  CONFLICTO ─────────────────►  🔧 REVISIÓN TÉCNICA
                                    (main queda intacto; reciclar rama)

  RAMA VACÍA ────────────────►  ↩️  REGRESAR A IMPLEMENTACIÓN
                                    (se creó rama pero no hay código)

  DUPLICADO ─────────────────►  📥 ARCHIVAR COMO ABSORBIDO
                                    (ya está en main; nada que hacer)
```

*Fin de la Guía Rápida.*
