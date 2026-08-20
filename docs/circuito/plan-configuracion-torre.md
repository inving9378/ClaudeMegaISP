# Plan — que todo control ajustable viva en la configuración de la Torre

> Basado en `inventario-de-controles.md` (2026-08-19). **Principio rector:** nada que cambie el
> comportamiento del circuito se ajusta por detrás. Si un control existe, se ve. Si está apagado, se
> ve apagado. Si no se puede tocar, se ve con candado y **dice por qué**.

---

## 1. Las tres cubetas, con criterio explícito

### 🟢 EDITABLE — se ajusta desde el panel, con permiso y auditoría

**Criterio:** gobierna *cuánto* o *cada cuánto*, y equivocarse es reversible con otro clic.

Techo global de automatización · sub-techos por actor · `autopilot.*` (enabled, umbral, reversible,
gracia) · `thomas.mecanico.tope_diario` · `thomas.enabled` · `auditor.*` (enabled, cap, cooldown,
umbral de cola, detectores) · `paralelismo` · `reaper.*` · umbrales del watchdog (tras migrarlos) ·
`ESCALACION_BUCLE_UMBRAL` (tras migrarlo) · `thomas.destrabe_bandeja.*` · `revisor.max_tokens` /
`brief_tokens` / modelos.

### 🔵 SOLO LECTURA — se muestra porque importa saberlo, se cambia en código

**Criterio:** o es una lista de términos cuya edición cambia *qué* bloquea el sistema (y merece un
diff revisado, no un textarea), o es un dato de forma, no de política.

Las **seis listas de términos** · `auditor.carriles` · `clasificador.reglas` ·
`revisor.perfil_path` · `RESUMEN_MAX` · `LOG_CAP` · los **permisos Spatie** del circuito ·
las **cadencias del crontab** (se muestran, se editan en el crontab) · `max_builds` (se muestra
diciendo que se ajusta por `.env`).

> **Por qué las listas de términos no son editables desde la web:** cambiar un término cambia qué
> escala y qué no. Eso es comportamiento de frontera, y ya costó dos incidentes. Un textarea en un
> panel no deja diff revisable; un commit sí.

### 🔴 NUNCA EXPUESTO — con su razón escrita

| Control | Razón |
|---|---|
| Separación **dev / prod** (`192.168.105.11` vs `.108`) | Un panel web que puede apagar la separación de entornos **es un control remoto para apagarla**. No existe endpoint que la toque |
| Prohibición de `migrate:fresh` | Ídem: es la protección contra destruir la base, no una preferencia |
| `guard()` de la **vía externa** (token Cowork/MCP) | Relajarlo desde la web le abriría a un token externo la aprobación de B/C. Si algún día cambia, es un trabajo con su discusión |
| `thomas.automerge.rutas_sensibles` / `patrones_destructivos` | Son la lista de lo que NUNCA se auto-mergea. Editable = desactivable |
| Los **cuatro topes duros** (prod · borrar datos · dinero · credenciales) | No se levantan desde ninguna configuración, ni con `autonomo`, ni con `override = auto` |
| **Tokens y llaves** (`ROADMAP_*_TOKEN`, `CLAUDE_API_KEY`, `AMI_SECRET`…) | Se listan por nombre con valor `[secreto]`. Nunca se muestran ni se editan |

**La frontera, dicha claro:** «todos los controles en un panel» **no** incluye los que apagan las
protecciones. Esos se pintan con candado y sin endpoint detrás.

---

## 2. Agrupación del panel — por ACTOR, no por tabla

| Grupo | Controles |
|---|---|
| **Automatización** | techo global · matriz · nivel efectivo por actor · override por item |
| **Thomas** | `enabled` · sub-techos mecánico y ya-decidido · tope diario · exige-reversible · cierre · esfuerzo |
| **Autopilot** | `enabled` · sub-techo · umbral de confianza · requiere-reversible · ventana de gracia |
| **Revisor** | modelos · tokens · denylist 🔵 · perfil 🔵 · listas de triaje C (tras migrar) |
| **Des-trabador** | cadencia 🔵 · límite por pasada · *(sin sub-techo hoy: lo gobierna el global)* |
| **Auditor** | `enabled` · cap · cooldown · umbral de cola · detectores · carriles 🔵 · spec |
| **Terminales** | paralelismo · nombres de worker · reaper · watchdog (tras migrar) · `max_builds` 🔵 |
| **Canal de respuesta** | **no existe todavía** — no se pinta (fase 5) |
| **Guardrails** | 🔒 los seis de la cubeta roja, con candado y su razón |

---

## 3. Cómo se ve «activo / inactivo»

> **CORRECCIÓN DE IRVING (2026-08-19), aplicada.** El panel **no muestra el flag `enabled`** como
> indicador principal. Un flag de habilitación es una **intención**; la última ejecución es un
> **hecho**. Regla, en una frase: **un motor está vivo si corrió bien hace poco, no si alguien dejó
> un booleano en `true`.**
>
> Y una segunda lección, medida el mismo día: **el latido tampoco basta solo.**
> `circuito:destrabar-bandeja` llevaba ocho días fallando cada minuto **pero acertó una corrida 13
> minutos antes** de que lo miráramos — un indicador de «última ejecución exitosa» decía «hace 13
> min, todo bien». Un fallo intermitente se esconde detrás de su propio éxito ocasional. Por eso un
> motor se pinta roto si **el latido está viejo O hay un fallo reciente**, y el fallo se guarda con
> su **mensaje**: «falló» sin decir qué no sirve para decidir nada.

Cada motor con **cinco datos**, no con un checkbox:

| Dato | Por qué |
|---|---|
| **Última ejecución exitosa** | el único dato que no puede mentir sobre si corrió |
| **Último fallo, con su mensaje** | lo que caza al intermitente, que el latido esconde |
| Cadencia / tope de horas | para saber si «hace 3 min» es normal o tarde |
| ¿Agendado en el crontab? | separa «no está agendado» de «corre y falla» |
| `enabled` | dato secundario, **nunca** el indicador principal |

```
circuito:destrabar-bandeja     hace 0.2 h    tope 2 h    agendado: (en el scheduler)
  🔴 último fallo 2026-08-19 19:36 — SQLSTATE[HY001] Out of sort memory
  se pierde: la bandeja no se destraba; nada se auto-mergea ni se auto-decide
```

**La prueba del indicador, hecha el 2026-08-19:** el destrabe **no salía en rojo — salía ausente.**
`circuito:destrabar-bandeja` ni siquiera estaba en `procesos_programados`, que cubría **4 de 13**
motores reales. El mecanismo estaba bien; el registro estaba incompleto, que es la misma forma de
fallar: algo que se ve sano porque nadie lo está mirando. Registrados los 7 que faltaban.

La infraestructura ya existe: `RoadmapCircuitoService::latidos()` + `config('circuito.procesos_programados')`
(#808) dan latido, cadencia, si está agendado y qué se pierde si no corre. **El panel las reusa; no
se inventa un segundo medidor.**

> Un motor apagado se ve apagado, no ausente. Y un motor **encendido que no late** se ve rojo — que
> es exactamente el caso que llevamos ocho días sin ver.

---

## 4. Fases, ordenadas por lo que ya existe

| Fase | Qué | Depende de | Riesgo |
|---|---|---|---|
| **1 — en curso** | `torre_config` · `TorreAutomationPolicy` · permisos · panel con techo global, sub-techos, los 2 parámetros vivos del auditor y guardrails · override por item · auditoría | — | **Medio-alto: toca el despacho.** Sustituye el tope de nivel en `scopeDespachable` y en 5 puntos de aprobación |
| **2** | 🔴 **Arreglar `destrabar-bandeja`** (`select *` → columnas acotadas) | ninguna | Medio: reactiva un motor dormido 8 días; la bandeja se moverá de golpe |
| **3** | Migrar huérfanos de alta prioridad: `ESCALACION_BUCLE_UMBRAL`, watchdog | fase 1 | Bajo: mover a config sin cambiar valores |
| **4** | Cadencias visibles: leer el crontab y mostrarlo 🔵 | #808 | Bajo: solo lectura |
| **5** | Motores inexistentes (canal de respuesta, heartbeat del auditor, veto de `rechazado`, ejes) | que existan | — |
| **6** | Migrar `revisor.alcance.denylist` a la frontera de Thomas | fase 1 | **Alto: cambia qué bloquea el revisor.** Exige el diff de qué empieza y qué deja de bloquearse |

**Regla dura de todas las fases: ningún control se pinta antes de que su motor exista.**

---

## 5. El candado contra la divergencia

| Control con más de una definición | Cómo se colapsa | Qué test truena |
|---|---|---|
| Techo de nivel (4 definiciones) | `TorreAutomationPolicy::techoGlobal()` única; los sub-techos son `min(global, sub)` | `TorreTechosCoherentesTest` — **desigualdad**: falla si `actor.max_nivel > techo_global`. Divergir hacia abajo es sano; hacia arriba, imposible |
| `autopilot.max_nivel` leído como techo global | Rename + lectura única | Test que falla si alguien vuelve a leer la clave vieja como techo global |
| Frontera dura (2 listas) | `ThomasService::categoriaFronteraDura()` como único detector | Test que falla si aparece una tercera lista de términos de frontera |
| Predicado de despacho | `RoadmapItem::sqlElegibleParaPool()` (ya hecho, 2A.5) | `PoolGuardCoherenceTest` + `circuito:coherencia-pool` |
| «Decisión muda» | `RoadmapItem::contarMudasEnLog()` (ya hecho, 2A.4) | — |

---

## 6. Migración de los huérfanos

**Se migran, en este orden:** `ESCALACION_BUCLE_UMBRAL` → watchdog (3 constantes) → listas de
triaje C del revisor → cadencias del crontab (solo lectura).

**Se dejan quietas, a propósito:**

- `RoadmapItem::SENALES_PRODUCCION` — es un guardrail; configurable = desactivable.
- `MergeRunner::LOCK` y las rutas de lock — son infraestructura, no política.
- `RESUMEN_MAX` / `LOG_CAP` — parámetros de forma. Migrarlos añade superficie sin añadir control.
- `revisor.model` (el alias de compat) — se muestra, no se edita: editarlo confunde con `model_routine`.

---

## 7. Costo y riesgo, en una línea

| Fase | Costo | Riesgo — sin optimismo |
|---|---|---|
| 1 | Alto | **Toca el despacho de trabajo.** Cinco puntos de aprobación cambian de fuente de verdad. Mitigación: valores iniciales = comportamiento actual, y el test de desigualdad |
| 2 | Bajo (una query) | Medio: al revivir, el destrabe procesará 8 días de bandeja en una corrida. Correr primero con `--limit` bajo |
| 3 | Bajo | Bajo: mover valores sin cambiarlos |
| 4 | Bajo | Nulo: solo lectura |
| 5 | Alto | Depende de motores que no existen |
| 6 | Medio | **Alto: cambia qué escala el revisor.** No se hace sin el diff revisado |

---

## 8. Lo que este plan NO resuelve

- **#794** — las 29 llamadas a `env()` en runtime. Mientras existan, el repo no puede cachear config.
- **#526** — el footprint «Sin clasificar» que serializa la flota.
- El **denylist con `Str::contains`**: se migra en la fase 6, no antes, porque cambia comportamiento.
