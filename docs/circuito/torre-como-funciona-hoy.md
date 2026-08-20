# La Torre de Control — cómo funciona HOY

> **Medido el 2026-08-19 en dev** (`192.168.105.11`, BD `megaisp`). Documento **descriptivo**: dice
> lo que el código hace, no lo que la documentación dice que hace. Donde los dos discrepan, gana el
> código y la discrepancia queda anotada en §6.
>
> Trabajo de **solo lectura**: ningún archivo de la aplicación fue modificado.

---

## 0. Resumen para el que llega con prisa

El circuito **no corre por rondas**. Corre continuo: un cron cada minuto (`circuito:scheduler`)
reparte trabajo a seis worktrees (`wt-1`…`wt-6`), y una terminal que termina jala el siguiente item
sin esperar a nadie.

**El scheduler no sólo reparte.** Dentro de su vuelta de cada minuto van enganchados —a propósito,
para que no haya una segunda carrera sobre los mismos items— el drenaje de merges, Thomas, el motor
de auditoría y el destrabe de la bandeja. Eso significa que **la cadencia real de esos cuatro no está
en el crontab**: está en sus throttles internos.

⚠️ **Y uno de esos cuatro lleva ocho días muerto sin que nada lo diga.** Ver §3.

---

## 1. Los actores

| Actor | Qué DECIDE | Qué NO decide | Autoridad |
|---|---|---|---|
| **`circuito:scheduler`** | Qué item toma cada terminal libre, respetando footprint disjunto | Nada de contenido: no juzga items | Único despachador (#432 B1) |
| **Thomas** (`ThomasService`) | Responde consultas de las terminales al instante (contrato = *exit code*); auto-aprueba el carril mecánico y el «ya decidido»; auto-mergea lo elegible | **No reparte trabajo.** No inventa dirección de negocio | Determinista, **sin llamada a IA**. Es el eslabón entre las 6 terminales e Irving |
| **Revisor** (`RevisorService`) | Veredicto adversarial `autoriza|escala` sobre items B; triaje de nivel; genera los briefs | No ejecuta; no mergea | IA (Sonnet rutina → Opus si borderline). **Falla-segura: si no puede, escala** |
| **Des-trabador** (`circuito:destrabe`) | Re-aprueba items de la bandeja que Opus juzga técnico/seguro | — | IA (Opus). ⚠️ **Sin tope de nivel** |
| **Autopilot** (`AutopilotService`) | Toma la opción `recomendada` del brief si trae `confianza` alta y `reversible` explícitos | Nada sin dato explícito: ausencia → Irving | Hasta `autopilot.max_nivel` (**C** hoy) |
| **Auditor** (`AuditorService`) | GENERA trabajo cuando la cola se seca: detecta gaps por módulo y crea los items que los cierran | No aprueba (nacen `pendiente_revision`); no reparte | Cap 10 items/ciclo, cola < 3, intervalo ≥ 15 min |
| **Terminales** (`wt-1`…`wt-6`) | Ejecutan el item: rama, código, commit, reporte | **Ya no pueden escalar a Irving por su cuenta** (regla de oro del prompt) | Claude CLI por OAuth, `--max-turns`, timeout 600 s |
| **Vía externa** (Cowork/MCP) | Ajusta `nivel_riesgo` y `estado_aprobacion` desde fuera, por token | **Solo un nivel A puede quedar `aprobado_claude` por aquí**; B y C topan en `requiere_irving` | `RoadmapCircuitoService::guard()` — carril separado, ver §5 del diagrama |

---

## 2. Qué dispara a cada actor — cadencia REAL

**Del crontab de `meganet` (leído, no supuesto):**

| Cadencia | Comando |
|---|---|
| cada minuto | `circuito:disparo-check --watch=58 --poll=3` |
| **cada minuto** | **`circuito:scheduler`** |
| cada 2 min | `circuito:revisar-backlog --apply --limit=6` |
| cada 2 min | `circuito:reap-stuck --minutes=25` |
| cada 2 min | `circuito:watchdog` |
| cada 4 min | `circuito:destrabe --limit=5` |
| cada 10 min | `circuito:brief-c --limit=2` |
| 06:20 diario | `circuito:priorizar-seguridad --limit=25` |
| 06:40 diario | `circuito:digest` |

**Enganchado DENTRO del scheduler** (no tiene línea de cron propia):

| Qué | Throttle | Dónde |
|---|---|---|
| `MergeRunner::drain()` | ninguno — cada vuelta | `SchedulerCommand:60` |
| `ThomasService::tick()` | ninguno — cada vuelta | `SchedulerCommand:86` |
| Motor de auditoría | cola < 3 **y** ≥ 15 min desde la última | `SchedulerCommand:106` |
| `circuito:destrabar-bandeja --apply` | 5 min | `SchedulerCommand:92` → `tickDestrabe()` |

**No agendado en ningún lado:** `circuito:re-triage` (el caducado del freno del clasificador). Ver
`docs/circuito/directiva-2a-cierre.md` §2 e item **#808**.

---

## 3. Qué está vivo y qué no

Medido con `RoadmapCircuitoService::latidos()` y las claves de `settings`, el 2026-08-19 18:51.

| Motor | Último latido | ¿Agendado? | Estado |
|---|---|---|---|
| `circuito:scheduler` | hace 1 min | sí | ✅ vivo |
| `circuito:watchdog` | hace 1 min | sí | ✅ vivo |
| Motor de auditoría | hace 11 min | (dentro del scheduler) | ✅ vivo |
| `circuito:priorizar-seguridad` | 06:20 de hoy | sí | ✅ vivo |
| `circuito:digest` | ayer 18:44 | sí | ✅ vivo |
| `circuito:re-triage` | **NUNCA** | **NO** | ⛔ no agendado (#808) |
| **`circuito:destrabar-bandeja`** | **2026-08-11 10:06 — hace 8.4 días** | (dentro del scheduler) | 🔴 **MUERTO, fallando cada minuto** |

### 🔴 El destrabe de la bandeja lleva 8 días fallando en silencio

```
SQLSTATE[HY001]: Memory allocation error: 1038 Out of sort memory,
consider increasing server sort buffer size
(SQL: select * from `roadmap_items` where … order by `id` asc limit 120)
```

- **Desde:** 2026-08-11 10:06. **Frecuencia:** 1 fallo por minuto — **1,440/día**, ~11,600 en total.
- **Por qué nadie lo vio:** `tickDestrabe()` traga la excepción (best-effort, por diseño: *«un fallo
  aquí nunca debe frenar el reparto»*) y la escribe en `roadmap_externo`, que hoy es una manguera.
  **1,129 de las 1,136 advertencias de hoy son esta.**
- **Causa raíz probable:** `select *` sobre `roadmap_items` (96 columnas, varias `JSON`/`LONGTEXT`)
  con `ORDER BY id LIMIT 120`, contra un `sort_buffer_size` de 262,144 B (256 KB).
- **Qué se perdió:** ese comando es el que auto-mergea lo verificado, auto-decide lo ya contestado y
  consolida lo estratégico. Es coherente con lo que hay hoy en la bandeja: **72 `aprobado_irving` +
  53 `requiere_irving` quietos**.
- **Es la octava instancia del mismo patrón**: un motor que se ve configurado y encendido, falla,
  y nada en el tablero lo dice. Exactamente #807 en otra capa.

> **No se arregló aquí**: este trabajo es de solo lectura y el arreglo toca el despacho. Item **#860**.

---

## 4. Latencias reales — por qué un item «no avanza»

Sumando los intervalos de los crons, en el **peor caso**:

| Camino | Peor caso |
|---|---|
| Item nivel **A** nace `pendiente_revision` → despachado | **≤ 1 min** (el scheduler lo ve de una: `A + pendiente_revision` es despachable) |
| Item **B** nace → revisor lo autoriza → despachado | 2 min (revisar-backlog) + 1 min (scheduler) = **≤ 3 min** |
| Item **C** nace → brief → autopilot → despachado | 10 min (`brief-c`) + autopilot (síncrono al escribirse el brief) + 1 min = **≤ 11 min** |
| Item en bandeja que sólo el des-trabador puede mover | **≤ 4 min** … *si el des-trabador acierta* |
| Item terminado esperando merge | **≤ 1 min** (drain va en cada vuelta) |
| Item con reclamo huérfano (worker muerto) | **≤ 25 min** por la vía lenta; **minutos** por la vía del flock |
| Item que sólo el destrabe de bandeja movería | **∞ hoy** — ese motor está muerto (§3) |
| Cola vacía → el auditor genera trabajo | **≤ 15 min**, y sólo si la cola reclamable está por debajo de 3 |

**El dato que más confunde:** un item puede estar «sin avanzar» simplemente porque su footprint
(`modulo`) está en vuelo en otra terminal. El pre-filtro serializa por ese campo, y un item con
`modulo` = «Sin clasificar» **bloquea a las seis terminales** mientras esté en vuelo (#432 B2, #526).

---

## 5. Dónde se atora un item, en la práctica

Estados terminales **de hecho** (no de diseño):

| Situación | Qué lo mantiene quieto | Qué lo destraba |
|---|---|---|
| `esperando_merge_irving = 1` | Nivel C con rama y commits: sale del pool y vive en Integración | `MergeRunner` (botón de la Torre o auto-merge de Thomas) |
| `bloqueado_por_bucle = 1` | 3 escalaciones seguidas con la MISMA huella | Un cambio material (otra opción, otra rama, otras preguntas) o destrabe manual |
| `excluir_pool_automatico = 1` | Master switch; lo encienden el anti-bucle y `requiere_sesion_supervisada` | Quitarlo a mano, o `forzar=true` al re-aprobar |
| `origen_bloqueo = 'humano'` | **Freno de Irving. NO caduca nunca** (2A.4) | Sólo Irving, desde la Torre. El digest se lo recuerda cada 7 días |
| `modulo` = «Sin clasificar» | Footprint desconocido ⇒ corre solo, bloqueando a las 6 | `circuito:clasificar-modulo` (#526) |
| `requiere_irving` sin brief | El autopilot no califica sin `confianza`/`reversible` | `circuito:brief-c` (10 min) o `rebrief-bandeja` |
| **Bandeja en general** | — | **`destrabar-bandeja`… que lleva 8 días muerto** |

---

## 6. Divergencias con `CONTEXTO-MEGAISP.md`

| Dice | Realidad |
|---|---|
| §8.2 «solo un item nivel A puede quedar `aprobado_claude`» | Cierto **sólo para la vía externa** (`guard()`). Internamente el autopilot llega a **C** desde el 2026-08-04 (#507) |
| §8.5 «6 pestañas en `ReleasesIndex.vue`» | Son **6** desde hoy: «Armar versión» salió de la navegación (`c9eccaf0`) |
| §8.4-quater «27 de 286 items con footprint desconocido» | Dato vivo, ya envejecido: hoy son otros números. Es de los que el propio protocolo dice no fijar en el doc |
| §8.4-ter «`circuito:auditor --apply` genera trabajo en vivo» | Correcto, pero el gating real vive en `debeCorrer()` y el motor **también** corre enganchado al scheduler |

### Lecciones documentadas que NUNCA se aplicaron

Ésta es la parte que importa: cosas que el repo ya sabe y siguen mal.

1. **§8.4-quinquies, lección 1 — «palabra completa, no substring».** Documentada tras dos
   incidentes. `ThomasService::apareceComoPalabra()` la aplica con la regex que respeta acentos…
   pero **`RevisorService::enAlcance()` sigue usando `Str::contains`** sobre los 40 términos de
   `circuito.revisor.alcance.denylist`. La lección se escribió y esa lista nunca se corrigió.
   → item de migración pendiente (ver `inventario-de-controles.md`, lista «controles que mienten»).
2. **§8.4-quinquies, lección 2 — «no distingue mención de negación».** Se corrigió en el triaje C
   (`RevisorService::TRIAJE_NEGACIONES`, commit `0ea1c52e`) pero **no** en el denylist ni en las
   señales del carril mecánico.
3. **§8.4-quater — el freno del footprint desconocido** está documentado como «el mayor freno de
   throughput que queda» y sigue abierto (#526).
4. **#790 — `config:cache`**: documentado, con candado (`config:auditar-env`), y **29 llamadas a
   `env()` en runtime siguen vivas** (#794 abierto).

---

## 7. Lo que este documento NO cubre

- El contenido de las listas de términos (van en `inventario-de-controles.md`).
- El ciclo de vida estado-por-estado (va en `flujo-item-real.md`).
- Qué debería ser configurable (va en `plan-configuracion-torre.md`).
