# Inventario de controles del circuito

> **Medido el 2026-08-19 en dev.** Todo lo que altera el comportamiento del circuito: claves de
> config, constantes en duro, crons, permisos, listas de términos y banderas de `settings`.
>
> La columna **¿Vivo?** está resuelta para todas las filas. Ninguna dice «por determinar».
> Los valores de secretos se listan por **nombre**, nunca su contenido.

**Leyenda de ¿Vivo?** · **vivo** = alguien lo lee y gobierna algo · **fantasma** = existe y nadie lo
lee (o lo lee un camino apagado) · **huérfano** = en duro, debería ser config.

---

## 1. Automatización y aprobación

| Control | Dónde vive | Valor hoy | Quién lo lee | Qué gobierna | ¿Vivo? | Quién lo cambia | ¿Exponible? |
|---|---|---|---|---|---|---|---|
| `torre_config.nivel_automatizacion` | tabla `torre_config` | `autonomo` | `TorreAutomationPolicy` | **Techo global**: hasta qué `nivel_riesgo` aprueba la máquina | vivo | UI (Entrega 1) | **sí** |
| `autopilot.max_nivel` | `config/circuito.php:177` | `C` | `AutopilotService:107,54,238` · `AutopilotCommand:40` · **`RoadmapItem::scopeDespachable:687`** | Nominalmente el autopilot; **de hecho el tope de despacho de TODO lo aprobado por máquina** | vivo | `.env` | **sí** (como sub-techo, tras el rename) |
| `autopilot.enabled` | `:168` | `true` | `AutopilotService` | Apaga el autopilot | vivo | `.env` | sí |
| `autopilot.umbral_confianza` | `:185` | `alta` | `AutopilotService:124` | Confianza mínima de la opción recomendada | vivo | `.env` | sí |
| `autopilot.requiere_reversible` | `:181` | `true` | `AutopilotService:126` | Exige `reversible:true` en B/C | vivo | `.env` | sí |
| `autopilot.ventana_gracia` | `:189` | `0` | `AutopilotService` | Minutos de espera desde el brief antes de decidir | vivo | `.env` | sí |
| `thomas.mecanico.max_nivel` | `:298` | `B` | `ThomasService:264` | Sub-techo del carril mecánico | vivo | `.env` | sí |
| `thomas.mecanico.tope_diario` | `:302` | `25` | `ThomasService` | Auto-aprobaciones mecánicas por día | vivo | `.env` | sí |
| `thomas.ya_decidido.max_nivel` | `config/circuito.php` (2B) | `C` | `TorreAutomationPolicy` | Sub-techo del carril «ya decidido» | vivo | `.env` | sí |
| `thomas.enabled` | `:213` | `true` | `ThomasService` | Apaga a Thomas entero | vivo | `.env` | sí |
| `thomas.exige_reversible_sin_recomendada` | `:259` | `true` | `ThomasService:139` | Si ninguna opción es reversible, escala | vivo | `.env` | sí |
| `circuito_pausado` | `settings` | `0` | todos los actores | **Kill switch global** | vivo | UI (botón Torre) | sí |
| `circuito_revisor` | `settings` | `1` | `scopeDespachable` | Si `aprobado_revisor` es despachable | vivo | UI | sí |
| `circuito_modo` | `settings` | `autonomo` | `circuito:flags` → `vuelta.sh` | Modo del ejecutor on-box | vivo | UI | sí |
| `circuito_modo_integracion` | `settings` | `auto-merge` | `MergeRunner` | Si el merge es automático o manual | vivo | UI | sí |
| `roadmap_items.automatizacion_override` | columna (2B) | `hereda` ×todos | `TorreAutomationPolicy` | Override por item | vivo | UI (Entrega 1) | sí |

## 2. Revisor y des-trabador

| Control | Dónde vive | Valor hoy | Quién lo lee | Qué gobierna | ¿Vivo? | Cambia | ¿Exponible? |
|---|---|---|---|---|---|---|---|
| `revisor.model_routine` | `:88` | `claude-sonnet-4-6` | `RevisorService:85` | Modelo de rutina | vivo | `.env` | sí |
| `revisor.model_hard` | `:89` | `claude-opus-4-7` | `RevisorService:86` | 2ª opinión y briefs | vivo | `.env` | sí |
| `revisor.model` | `:87` | `claude-sonnet-4-6` | fallback de `model_routine` | Compat | vivo (fallback) | `.env` | solo lectura |
| `revisor.max_tokens` | `:90` | `700` | `RevisorService:120` | Tokens del veredicto | vivo | `.env` | sí |
| `revisor.brief_tokens` | `:91` | `1100` | `RevisorService` (×3 en briefs) | Tokens del brief | vivo | `.env` | sí |
| `revisor.alcance.denylist` | `:~100` | **40 términos** | `RevisorService::enAlcance:59` | Pre-filtro: qué NO evalúa la IA | vivo | `.env`/código | **solo lectura** — ver §«mienten» |
| `revisor.perfil_path` | `:~93` | `docs/perfil-decisiones-irving.md` | `RevisorService::perfilIrving` | Perfil inyectado al prompt | vivo | código | solo lectura |
| `RevisorService::TRIAJE_C_PLAIN` | `RevisorService.php:251` | lista en duro | `triajeC` | Términos que fuerzan nivel C | **huérfano** | editar código | sí, tras migrar |
| `RevisorService::TRIAJE_C_WORD` | `:260` | 6 términos | `triajeC` | Ídem, con palabra completa | **huérfano** | editar código | sí, tras migrar |
| `RevisorService::TRIAJE_NEGACIONES` | `:269` | lista en duro | `triajeC` | Detecta negación cercana | **huérfano** | editar código | sí, tras migrar |
| `RevisorService::TRIAJE_VENTANA_NEGACION_BYTES` | `:285` | `90` | `triajeC` | Ventana de la negación | **huérfano** | editar código | sí |

## 3. Auditor (generador de trabajo)

| Control | Dónde vive | Valor hoy | Quién lo lee | Qué gobierna | ¿Vivo? | Cambia | ¿Exponible? |
|---|---|---|---|---|---|---|---|
| `auditor.enabled` | `:545` | `true` | `AuditorService::habilitado` | Kill switch propio | vivo | `.env` | sí |
| `auditor.cap_por_ciclo` | `:552` | `10` | `AuditorService::ciclo` | Items por corrida | vivo | `.env` | **sí (Entrega 1)** |
| `auditor.min_intervalo_minutos` | `:577` | `15` | `AuditorService::debeCorrer` | Cooldown entre escaneos | vivo | `.env` | **sí (Entrega 1)** |
| `auditor.umbral_cola` | `:570` | `3` | `AuditorService::debeCorrer` | Genera sólo si la cola está por debajo | vivo | `.env` | sí |
| `auditor.items_por_modulo_por_ciclo` | `:563` | `2` | `AuditorService:1178` | Round-robin entre módulos | vivo | `.env` | sí |
| `auditor.carriles.paralelo` / `.serializado` | `:~590` | 22 + 4 módulos | `modulosAAuditar` | Qué módulos se auditan y en qué orden | vivo | `.env`/código | sí |
| `auditor.detectores.*` (6) | `:618-623` | todos `true` | `detectarGaps` | Qué gaps se buscan | vivo | `.env` | sí |
| `auditor.terminos_producto` | `:~600` | 11 términos | `AuditorService:941` | Qué gap va a Irving en vez de a la cola | vivo | código | solo lectura |
| `auditor.excluir_modulos` | `:~612` | `Demo, Security, Voice` | `modulosAAuditar` | Módulos que nunca se auditan | vivo | código | sí |
| `auditor.spec.*` (9) | `config` (2B) | umbral 30 · cap 25 | `medirContraSpec` | Detectores de declaración | vivo | `.env` | sí |

## 4. Despacho, reaper y watchdog

| Control | Dónde vive | Valor hoy | Quién lo lee | Qué gobierna | ¿Vivo? | Cambia | ¿Exponible? |
|---|---|---|---|---|---|---|---|
| `paralelismo` | `:135` | `6` | `getParalelismo` | Cuántas terminales | vivo | `.env` | sí |
| `continuo` | `:18` | `true` | `esContinuo` | Modo continuo vs rondas | vivo | `.env` | sí |
| `interval_min` | `:35` | `30` | `proximaVueltaAt:1198` · `RoadmapController:182,229` | Estimación de «próxima vuelta» | **fantasma** — `proximaVueltaAt` devuelve `null` si `continuo` (y lo es) | `.env` | **nunca** (ver §«mienten») |
| `max_builds` | `:136` | `3` | **nadie en PHP**; `deploy/circuito/npm-build.sh` lee el **env** `CIRCUITO_MAX_BUILDS` | Builds npm simultáneos | **fantasma** (la clave) / vivo (el env) | `.env` | solo lectura |
| `pausa_aviso_horas` | `:44` | `3.0` | `RoadmapCircuitoService:179` | Aviso de pausa olvidada | vivo | `.env` | sí |
| `desconocido_diferido` | `:67` | `true` | `ejecutablesParalelo` | Difiere el footprint desconocido | vivo | `.env` | sí |
| `reaper.max_reintentos` | `:446` | `3` | `reap-stuck` | Reintentos antes de escalar | vivo | `.env` | sí |
| `reaper.gracia_minutos` | `:459` | `3` | `reap-stuck` | Gracia antes de liberar | vivo | `.env` | sí |
| `WatchdogService::SCHEDULER_STALE_SEG` | `WatchdogService.php:42` | `180` | watchdog | Cuándo el scheduler se da por caído | **huérfano** | editar código | sí |
| `WatchdogService::WORKER_HUNG_SEG` | `:45` | `600` | watchdog | Cuándo un worker se da por colgado | **huérfano** | editar código | sí |
| `WatchdogService::MAX_INTENTOS` | `:48` | `3` | watchdog | Reintentos de auto-recuperación | **huérfano** | editar código | sí |
| `WatchdogService::LOG_CAP` | `:51` | `60` | watchdog | Tamaño del log en `settings` | **huérfano** | editar código | solo lectura |
| `RoadmapItem::ESCALACION_BUCLE_UMBRAL` | `RoadmapItem.php:126` | `3` | `contarEscalacion` | **Cuántas escalaciones iguales activan el anti-bucle** | **huérfano** | editar código | **sí** — gobierna cuándo un item sale del pool |
| `RoadmapReportService::RESUMEN_MAX` | `:27` | `12000` | espejo de `comentarios_claude` | Tamaño del espejo legible | **huérfano** | editar código | solo lectura |

## 5. Thomas — merge, consolidado y esfuerzo

| Control | Dónde vive | Valor hoy | Quién lo lee | Qué gobierna | ¿Vivo? | ¿Exponible? |
|---|---|---|---|---|---|---|
| `thomas.automerge.enabled` | `:349` | `true` | `elegibleAutoMerge` | Auto-merge | vivo | sí |
| `thomas.automerge.cap_por_ciclo` | `:353` | `5` | `DestrabarCommand:44` | Merges por ciclo | vivo | sí |
| `thomas.automerge.rutas_sensibles` | `:~356` | 7 patrones | `elegibleAutoMerge` | Qué rutas NUNCA auto-mergean | vivo | **solo lectura** (es un guardrail) |
| `thomas.automerge.patrones_destructivos` | `:~360` | 6 patrones | `elegibleAutoMerge` | Qué diffs bloquean el auto-merge | vivo | **solo lectura** (guardrail) |
| `thomas.destrabe_bandeja.enabled` | `:395` | `true` | `SchedulerCommand::tickDestrabe` | El destrabe automático | vivo (**pero el comando falla**) | sí |
| `thomas.destrabe_bandeja.intervalo_minutos` | `:396` | `5` | `tickDestrabe` | Throttle | vivo | sí |
| `thomas.destrabe_bandeja.limit` | `:397` | `120` | `tickDestrabe` | Tamaño del lote | vivo — **y es la causa del fallo** | sí |
| `thomas.consolidado.*` (3) | `:413-` | 48 h | `ThomasService` | Consolidado de lo estratégico | vivo | sí |
| `thomas.cierre.exige_reporte_coloquial` | `:428` | `true` | `ThomasService:871` | Exige reporte para cerrar | vivo | sí |
| `thomas.cierre.exige_enlace_revision` | `:429` | `true` | `ThomasService:871` | Exige deep-link para cerrar | vivo | sí |
| `thomas.esfuerzo.*` (6) | `:~` | A20/B45/C90 | `ThomasService:836` | Estimación de ETA | vivo | sí |
| `clasificador.reglas.*` | `:~` | 15 módulos | `ClasificarModuloCommand` | Footprint automático | vivo | sí |

## 6. Listas de términos — **hay seis, en dos casas y con dos semánticas**

| Lista | Dónde | Tamaño | Cómo matchea | ¿Vivo? |
|---|---|---:|---|---|
| `thomas.escalamiento` (prod · borrar · dinero · credenciales) | config | 12+9+15+15 | **palabra completa**, regex con acentos | vivo — es LA frontera dura |
| `thomas.mecanico.senales` | config | 35 | palabra completa | vivo |
| `thomas.mecanico.negocio` | config | 20 | palabra completa | vivo |
| `revisor.alcance.denylist` | config | 40 | ⚠️ **`Str::contains` = SUBSTRING** | vivo |
| `auditor.terminos_producto` | config | 11 | `str_contains` | vivo |
| `RevisorService::TRIAJE_C_*` + `TRIAJE_NEGACIONES` | **en duro** | ~20 | mixto | vivo, huérfano |
| `RoadmapItem::SENALES_PRODUCCION` | **en duro** | 6 | `stripos` | vivo, huérfano |

## 7. Crons y permisos

Cadencias: ver `torre-como-funciona-hoy.md` §2. **Ningún cron tiene config asociada**: cambiar una
cadencia exige editar el crontab de `meganet`. Son controles **huérfanos por definición**.

| Permiso Spatie | Qué habilita | ¿Exponible? |
|---|---|---|
| `circuito.decidir` | Aprobar/rechazar items, integrar, ahora también `armar-version` | solo lectura |
| `circuito.disparar` | Botón «Jalar trabajo ahora» | solo lectura |
| `circuito.pause` | Kill switch (#342: sólo por este permiso) | solo lectura |
| `roadmap_view` / `roadmap_manage` | Ver/gestionar la Hoja de Ruta | solo lectura |
| `roadmap_ia_consultar` | Consultas de IA desde la Torre | solo lectura |
| `updates.apply` | Aplicar actualizaciones de instancia | solo lectura |
| `torre.config.view` / `.edit` | **Pendientes** — Entrega 1 | — |

Tokens (`ROADMAP_EXTERNAL_READ_TOKEN`, `WRITE_TOKEN`, `CREATE_TOKEN`, `MCP`): **valor `[secreto]`**,
sólo en `.env`. **Nunca exponibles.**

---

# Las tres listas que son el valor del inventario

## 🔴 1. Controles FANTASMA — existen y nadie los lee

| Control | Por qué es trampa |
|---|---|
| **`circuito.max_builds`** | Ningún PHP lo lee. El semáforo de builds lo aplica `deploy/circuito/npm-build.sh` leyendo el **env** `CIRCUITO_MAX_BUILDS` directo. Editar `config/circuito.php` aquí no hace absolutamente nada; hay que tocar el `.env`. |
| **`circuito.interval_min`** | Se lee en tres sitios, pero el único que decide (`proximaVueltaAt`) **devuelve `null` antes de mirarlo** porque `continuo = true`. Alimenta un campo de la Torre que hoy no significa nada. Documentado como DEPRECADO en `CONTEXTO §8.1` y sigue ahí. |

## 🟠 2. Controles HUÉRFANOS — en duro, gobiernan comportamiento

| Control | Valor | Gobierna | Prioridad de migrar |
|---|---|---|---|
| `RoadmapItem::ESCALACION_BUCLE_UMBRAL` | `3` | Cuántas escalaciones sacan un item del pool | **alta** — es el que más items congela |
| `WatchdogService::WORKER_HUNG_SEG` | `600` | Cuándo se mata un worker colgado | alta |
| `WatchdogService::SCHEDULER_STALE_SEG` | `180` | Cuándo se da por caído el scheduler | media |
| `WatchdogService::MAX_INTENTOS` | `3` | Reintentos de auto-recuperación | media |
| `RevisorService::TRIAJE_C_*` (3 listas + ventana) | — | Qué items se fuerzan a nivel C | media |
| `RoadmapItem::SENALES_PRODUCCION` | 6 literales | El aviso «esto toca prod» | baja (es un guardrail) |
| `RoadmapReportService::RESUMEN_MAX` | `12000` | Tamaño del espejo | baja |
| **Las 9 cadencias del crontab** | — | Cuándo corre cada motor | **alta** — hoy no se ven en ningún lado |

## 🟡 3. Controles que MIENTEN — el nombre no corresponde

| Control | Dice | Hace |
|---|---|---|
| **`autopilot.max_nivel`** | «tope del autopilot» | Gobierna **a todos los actores**: `scopeDespachable:687` lo usa como tope de despacho de todo lo aprobado por máquina. Ya en curso de rename en la Entrega 1 |
| **`revisor.alcance.denylist`** | «frontera dura» | Es una **segunda** frontera, distinta de `thomas.escalamiento`, con **40 términos y `Str::contains` (substring)** — el bug que `CONTEXTO §8.4-quinquies` documenta desde `#338` y que nunca se corrigió. «Los topes duros» significan dos cosas distintas según a quién le preguntes |
| **`thomas.destrabe_bandeja.enabled = true`** | «el destrabe está encendido» | Está encendido y **el comando falla cada minuto desde hace 8 días**. Encendido ≠ funcionando |
| **`interval_min`** | «cada cuánto corre una vuelta» | No hay vueltas. El scheduler corre cada minuto y jala continuo |
