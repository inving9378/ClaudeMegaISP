# Reporte de la noche del 2026-08-25/26 — recuperación del P0 y Paso 0 del Supervisor

> Sesión sin supervisión. Base de dev restaurada por PITR horas antes. **El freno quedó puesto**
> (`storage/app/circuito/PAUSA`), como se ordenó. El circuito NO se reanudó.

---

## 1. Bloques: qué se completó y qué no

| Bloque | Estado | Motivo |
|---|---|---|
| **0 — Respaldo** | ✅ completo | `megaisp-post-pitr-20260825-1912.sql.gz`, 142 MB, `gunzip -t` OK, 502 `CREATE TABLE`, cierre `Dump completed` (no truncado). |
| **1 — Sellar la causa raíz** | ⚠️ **completo con una excepción declarada** | 1.2, 1.3 y 1.4 verdes y verificados. En 1.1, **3 de 16 worktrees no se pudieron mergear** por conflicto de contenido y se dejaron sin resolver, como indica el propio 1.1. Ver §1.1. |
| **2 — Higiene post-incidente** | ✅ completo | Restauración verificada, cron restaurado, zombis resueltos, #171 reconciliado, bitácora escrita. |
| **3 — Hoja de Ruta** | ✅ completo | 8 items (5 pedidos + 3 hallazgos de la noche). §5. |
| **4 — Supervisor, Paso 0** | ✅ completo | Read-only, sin escrituras. §4. |
| **5 — Supervisor, Fase 1** | ⛔ **NO se hizo, a propósito** | La regla era "solo si los bloques 0–4 quedaron verdes". No lo están: 3 worktrees sin mergear, y sobre todo **la suite todavía no puede correr entera** (`megaisp_test` se queda en 236 de 502 tablas) y **la cola no tiene worker** (143 jobs sin consumir). Construir la Fase 1 sobre eso sería construir sin red. |

### 1.1 — La excepción, declarada explícitamente

Hay una tensión entre dos instrucciones y la resolví con criterio, así que lo digo abierto:

- La **condición de salida del Bloque 1** pide que *todos* los `phpunit.xml` digan `megaisp_test`.
- El **sub-paso 1.1** dice: *"si alguno no es trivial, déjalo sin resolver, anótalo y sigue con los
  demás — un worktree sin mergear es un hallazgo, no un fracaso."*

Seguí 1.1 y continué, por una razón concreta: **el propósito de la condición está cubierto por un
mecanismo más fuerte que el merge.** El candado del wrapper (§1.2) no depende de la rama, y lo probé
deliberadamente contra uno de esos tres worktrees exactos: aborta. Los tres tampoco son slots del
circuito (`wt-1`…`wt-6`), así que `vuelta.sh` no opera nunca sobre ellos.

Si no compartes el criterio, el trabajo de los bloques 2–4 es independiente y reversible.

---

## 2. Evidencia de 1.4 — la suite ya no toca `megaisp`

Corrida real de `phpunit` desde `/home/meganet/circuito/wt-4` (worktree de un slot del circuito, en
su rama `circuito/item-1035-…`), midiendo la base antes y después:

| | antes | después |
|---|---|---|
| `megaisp` | **502 tablas** | **502 tablas** |
| `megaisp_test` | 0 tablas | **236 tablas** |
| canario `roadmap_items` | 223 filas · última mod `2026-08-25 18:13:31` | 223 filas · `2026-08-25 18:13:31` |

Las dos mitades importan. `megaisp` quedó idéntica, canario incluido. Y `megaisp_test` pasó de 0 a
236 tablas, o sea **el `migrate:fresh --seed` sí se ejecutó** — la operación destructiva ocurrió y
aterrizó donde debía. Sin ese segundo dato, "no pasó nada" también sería compatible con que la suite
no hubiera corrido.

**Prueba deliberada del candado del wrapper** (4 casos, log en `storage/app/circuito/guard-bd-pruebas.log`):

1. `main` → pasa (apto).
2. `megaisp-wt-tablero` (worktree real, rama 791 commits atrás, `phpunit.xml=megaisp`) → **aborta**.
3. Árbol sin `phpunit.xml` → **aborta** (fail-closed: no poder averiguarlo es el caso peligroso).
4. `cron-wrap.sh circuito:flags` con `main` sano → corre normal (`pausado=1`).

---

## 3. Estado de cada worktree tras el merge

13 de 16 quedaron con `DB_DATABASE=megaisp_test` **y** `tests/GuardBaseDePruebas.php`.

| worktree | rama | resultado |
|---|---|---|
| `wt-1` | `circuito/item-171-…` | merge OK |
| `wt-4` · `wt-5` · `wt-6` | items 1035 / 1006 / 1014 | merge OK (fast-forward) |
| `wt-2` · `wt-3` · `wt-exec` · `megaisp-wt338` | detached | **resincronizados a `main`** (un merge sobre HEAD suelto se perdería) |
| `wt-piezaC` · `wt-piezaC-gate` | Pieza C | merge OK |
| `megaisp-wt-actividad` · `megaisp-wt-ui` · `megaisp-wt-validacion` | varias | merge OK |
| **`circuito-fase-a`** | `circuito/fase-a-anti-bucle` | ⚠️ **CONFLICTO** (`DestrabeCommand.php`, `IntegrarItemCommand.php`) — 720 commits atrás |
| **`megaisp-wt-fase1`** | `circuito/fase1-metadata-decisiones` | ⚠️ **CONFLICTO** (`DestrabeCommand.php`, `RoadmapItem.php`) — 720 atrás |
| **`megaisp-wt-tablero`** | `circuito/c2` | ⚠️ **CONFLICTO** (`CLAUDE.md`, `RoadmapController.php`) — 791 atrás |

Los tres merges se abortaron limpiamente (`git merge --abort`); ningún worktree quedó con marcadores
de conflicto. Item **#231** propone qué hacer con ellos.

---

## 4. Paso 0 del módulo Supervisor (read-only, sin escrituras)

**0. Estado post-incidente:** ✅ `megaisp` con **502 tablas**, 4.786 usuarios, 223 items,
**784 migraciones aplicadas / 0 pendientes** (incluidas las dos del 25-ago 17:00 y 18:00: el PITR
recuperó hasta el esquema de minutos antes del borrado). Candado `04ec4395` en `main`,
`phpunit.xml` → `megaisp_test`, freno puesto, cron restaurado (11 líneas, idénticas al respaldo).

**1. ¿Es dev?** ✅ `192.168.105.11` / `38.123.192.199`, `APP_ENV=local`,
`APP_URL=http://192.168.105.11`. En ningún momento se tocó `.108`.

**2. ¿Existe un módulo Supervisor?** **No.** 0 coincidencias en `module_registry` (46 módulos
registrados) y no existe `app/Modules/Addons/Supervisor`. Contrato vigente de `module.json`, tomado
del módulo Roadmap: `slug · name · version · description · type · dependencies · active ·
permissions · menu · sidebar · admin_cards · config_sections · api_endpoints · ai · screens ·
client_tab · service_type`. ⚠️ `keep_data` **sí** es parte del contrato pero casi no se usa: sólo
`PortalPago` lo declara. La tabla `module_registry` tiene `id, slug, name, installed_version, type,
active, installed_at` — **no** guarda permisos ni menús: eso vive en el `module.json`.

**3. Hoja de Ruta.** 231 items.

| estado | A | B | C | sin nivel |
|---|---:|---:|---:|---:|
| `aprobado_irving` | – | 65 | 23 | 9 |
| `completado` | – | 2 | – | 89 |
| `aprobado_revisor` | – | 14 | 1 | – |
| `pendiente_revision` | 10 | 4 | – | 2 |
| `requiere_irving` | – | 4 | 4 | – |
| `cancelado` | – | – | – | 4 |
| `en_progreso` | – | – | – | – |

**`en_progreso` quedó en 0.** Hay **104 items sin `nivel_riesgo`**, pero 93 de ellos están cerrados
(`completado`/`cancelado`): la superficie viva del defecto son **11** (9 `aprobado_irving` + 2
`pendiente_revision`). Items con `origen_item_id` poblado: 5.

**⚠️ La migración del motor auditor está A MEDIAS — prerequisito bloqueante de la Fase 4.**
`roadmap_items` tiene 91 columnas. Existen `origen_item_id` (`bigint unsigned`, nullable) y
`auditor_fingerprint` (`varchar(64)`). **NO existen `tipo`, `hallazgo_firma` ni `auditoria_ciclo`**,
y no hay ninguna migración en disco que las cree. Cuidado con un falso positivo: las referencias a
`tipo` que aparecen en el código del circuito son de **`roadmap_item_reports.tipo`**, que es otra
tabla y otro significado. Registrado como item **#232** (nivel C, con sus decisiones pre-resueltas).

**4. Dependencias externas.**

- **Evolution:** hay **una sola** instancia registrada, `meganet-ventas` (producción, `active=1`,
  `default_instance=0`), y su estado es **`disconnected`**. **No queda ninguna instancia libre** para
  el Supervisor → confirma el riesgo #2 de la spec: hace falta un número dedicado, no reusar el de
  ventas.
- **Whisper:** **no hay nada en este box.** Sin binario (`which whisper` vacío), sin `/opt/whisper*`,
  sin referencias en código ni en `.env`, y **sin `/var/lib/asterisk/agi-bin`**. Asterisk sí está
  instalado (`/usr/sbin/asterisk`) y el bot de voz existe en código (`IaBotController`, tablas
  `ia_bot_*`), pero **el pipeline AGI de MegaVoz no está montado aquí**. O sea: el driver
  `whisper_local` de la spec **no tiene sustrato**; hoy sólo es viable `openai_api`. Eso además
  coincide con la recomendación del riesgo #4 (no cargar el disco con modelos nuevos).
- **Cola:** driver `database`. **143 jobs pendientes en `default` y 1 en `video-render`, y NINGÚN
  worker corriendo.** `supervisord` está vivo (pid 788) con `megaisp-queue.conf` en `conf.d`, pero
  ningún proceso `queue:work` existe. El job más viejo es del **24-ago 12:58** y el más nuevo de
  **hace minutos** (`ClasificarRiesgoJob`, encolado por el `revisar-backlog` del cron que restauré).
  46 `failed_jobs`, todos de junio (ruido viejo, no del incidente).

> **Lo que esto significa para el Supervisor:** sus fases 2, 4 y 5 son jobs (`TranscribirIdeaJob`,
> `VerificarItemJob`, `WatchdogJob`, `BriefDiarioJob`). **Sin worker, todas serían un no-op
> silencioso** — exactamente la familia de fallo que la spec describe en §15.3. Y hay un detalle
> incómodo: el triaje del circuito ya está en ese estado hoy, porque `ClasificarRiesgoJob` se encola
> y nadie lo consume.

---

## 5. Items registrados

| # | nivel | estado | título |
|---|---|---|---|
| **225** | B | `pendiente_revision` | Inventario y candado de los recursos globales que comparten las seis terminales **(el más importante)** |
| **226** | B | `requiere_irving` | [INFRA] Renovar el cert de `dev.meganett.com.mx` con hook DNS-01 automatizado |
| **227** | B | `requiere_irving` | [INFRA] Exentar de la detección de DOS del MikroTik `dst=38.123.192.199 dport=443` |
| **228** | B | `pendiente_revision` | Chequeo `bd_integra` en la vigilia de Thomas: base vacía = freno automático, luego aviso |
| **229** | B | `pendiente_revision` | Extender la API `roadmap-externo` (Opción 2): alta de items e historial |
| **230** | B | `pendiente_revision` | `megaisp_test` no se puede construir sólo con migraciones (236 de 502 tablas) |
| **231** | B | `requiere_irving` | Podar los worktrees muertos (3 sin mergear, 5 con +700 commits de atraso) |
| **232** | **C** | `requiere_irving` | Falta la migración del motor auditor: sin `tipo`, `hallazgo_firma` ni `auditoria_ciclo` |

Los ocho nacieron completos: `nivel_riesgo` nunca nulo, `modulo` de la lista existente (sin inventar
valores nuevos, por el drift del #526), `reporte_coloquial` en lenguaje llano, bloque de **Canal de
respuesta** anexado literal, y los que requieren decisión salieron del pool automático. El #232, por
ser nivel C, lleva sus **tres decisiones ya resueltas** con recomendación, para que ratifiques o
corrijas en vez de arrancar de cero.

**No registré como item** la falta de worker de cola: no es una tarea de código sino una acción de
operación con `sudo` que sólo tú puedes hacer. Va en la lista de decisiones (§6).

---

## 6. Lo que tienes que decidir mañana

1. **Levantar el worker de la cola.** 143 jobs sin consumir desde el 24-ago; el triaje del circuito
   está inerte aunque quites el freno. `supervisord` corre pero su programa de cola no.
   *Recomendación:* `sudo supervisorctl status` y reiniciar `megaisp-queue` **antes** de reanudar el
   circuito. Reanudar sin worker es reanudar a medias, y en silencio.
2. **Reanudar o no el circuito.** No lo toqué, como ordenaste. *Recomendación:* reanudar **después**
   del punto 1 y de mirar el #230 — con la suite sin poder correr, el circuito trabaja sin red.
3. **Los tres commits del item #171.** Su spec pedía "debe fallar cerrado", que es lo que agravó el
   incidente. *Recomendación:* descartar `3dee9dda` y `f223d940` (superados por `a2a598f7`, y su test
   contradice a `main` — verificado: el merge da conflicto), y **rescatar sólo `64296733`**, que
   cierra en código el botón *Run migrations* de Ignition y cubre la mitad del spec que `main` no
   cubre.
4. **Worktrees muertos (#231).** *Recomendación:* quitar los tres en conflicto y `wt-exec`,
   **conservando las ramas** (`git worktree remove` no borra commits). Los `wt-piezaC*` se conservan.
5. **Cómo se construye `megaisp_test` (#230).** *Recomendación:* sembrarlo desde el esquema de
   `megaisp` para desbloquear la suite hoy, y dejar el catálogo versionado idempotente como el
   trabajo de fondo. En ese orden y como dos items.
6. **Las tres decisiones del #232** (la migración del motor auditor). Ya vienen resueltas:
   `varchar(20)` en vez de enum · default `manual` para las 223 filas · reusar `auditor_fingerprint`
   en vez de crear `hallazgo_firma`. *Recomendación:* ratificarlas tal cual; son el prerequisito de
   la Fase 4 del Supervisor.
7. **Número dedicado de Evolution para el Supervisor** (riesgo #2 de la spec). Confirmado que no hay
   instancia libre: la única registrada es la de ventas y está `disconnected`.
   *Recomendación:* número nuevo, como dice la spec. Mezclarlo con `meganet-ventas` ensuciaría la
   conciliación ya sellada.
8. **Fase 1 del Supervisor:** no la empecé (§1). *Recomendación:* abrirla después del #225
   (inventario de recursos compartidos), que es el orden que la propia spec defiende en su riesgo #8:
   aislamiento y verificación antes que autonomía.

---

## 7. Commits de la noche

| hash | qué |
|---|---|
| `bcf478c2` | El candado de la base de pruebas deja de depender de la rama (wrapper + `vuelta.sh` + `cron-wrap.sh`) |
| `a2a598f7` | El guardrail de migraciones deja de depender de la base que protege (+ prueba con la conexión caída) |
| `2e1448db` | Bitácora del P0: causa, PITR y por qué la primera ocurrencia no previno la segunda |

(El candado de PHP, `04ec4395`, es de la sesión anterior.)
