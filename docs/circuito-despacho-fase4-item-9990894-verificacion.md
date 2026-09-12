# Item #9990894 — Fase 4 (C3/C4/C5): leer el log de despacho y decidir tope de concurrencia/frenos

Sub-item de seguimiento de #9990863 (Fase 4). Depende de que Fase 2 (#9990892) y Fase 3
(#9990893) estén mergeadas a `main` y de tener ya horas/días reales de datos en el canal
`circuito_despacho` — verificado ambas cosas antes de tocar nada:

- `#9990892` → `completado`, `merge_commit=1cff95fa` (ancestro de `main`).
- `#9990893` → `completado`, `merge_commit=9153ce38` (ancestro de `main`).
- `storage/logs/circuito-despacho-*.log` **solo existe en el checkout principal**
  (`/var/www/megaisp`) — cada worktree del circuito tiene su propio `storage/` sin ese archivo,
  porque el scheduler que escribe al canal (`SchedulerCommand`, `app/Modules/Addons/Roadmap/
  Console/SchedulerCommand.php:409`) corre sobre el checkout principal, no sobre los worktrees.
  Se leyó el log del checkout principal (solo lectura, sin editar/artisan/git ahí — no viola el
  aislamiento #334).
- Rango real: `2026-09-11 23:46:04` → `2026-09-12 17:13:05` (~17.5 horas, 5,974 líneas,
  1,044 ciclos de polling distintos) — cumple "varias horas reales", no hace falta esperar más.

## Frecuencia real de cada código (motivoNoDespachable / diagnosticoCeroDespacho)

De los 15 códigos que el item pide contar, en 17.5h reales **solo aparecieron 3**:

| Código                     | Líneas | Items distintos detrás |
|----------------------------|-------:|-------------------------|
| `dependencia_sin_cerrar`   | 3,027  | 5 (#788 Flotas, #9990418/#9990419 Circuito CC, #9990534/#9990538 Mapa de Red) |
| `bloqueado_por_dependencia`| 1,661  | 4 (#9990361 Talento, #9990420/#9990425 Circuito CC, #9990460 Mapa de Red) |
| `desarrollo_humano`        | 1,286  | 2 (#170, #652 — ambos Circuito CC) |

Los otros 12 (`ya_cerrado`, `descartado`, `freno_humano`, `esperando_merge`, `agendado`,
**`footprint_desconocido`**, `sesion_supervisada`, `fuera_del_pool`, `en_progreso`,
`no_despachable`, **`tope_modulo`**, `sin_candidatos`) tuvieron **0 ocurrencias**. 100% de las
5,974 líneas encajan en el patrón `wt-K ociosa: #ID (modulo=X) — codigo: mensaje` (0 líneas sin
match), así que no hay ruido de formato escondiendo otro código.

Muestra directa (2026-09-12 12:00:08): **las 6 terminales** (wt-1..wt-6) estaban ociosas
simultáneamente, las 6 por `dependencia_sin_cerrar`/`bloqueado_por_dependencia` — nunca por
`footprint_desconocido` ni `tope_modulo`.

## Decisión C3/C4 — NO tocar el tope de concurrencia

El item condiciona el ajuste de `paraleloMismoModulo()`/`getParalelismo()` a que domine
`footprint_desconocido`/`tope_modulo`. **No dominan — no aparecen ni una vez** en 17.5h de
datos reales. Conclusión: el ocio de las 6 terminales en este periodo es 100% backlog real de
dependencias entre items (cadenas de fases que se esperan unas a otras) y trabajo humano en
curso, **no** un techo de paralelismo demasiado bajo. Subir `circuito_paralelismo` (hoy en 6,
default de `config/circuito.php`) o `paralelo_mismo_modulo` (hoy en 2, `torre_config` en BD) no
resolvería nada de lo que el log muestra — sería tocar una perilla sin evidencia que lo pida,
justo lo que el propio item prohíbe ("no subir a ciegas"). **Se registra como decisión: no se
cambia ningún valor de concurrencia.**

Documentación de dónde vive cada perilla (para que quede localizable sin re-grepear):
- `circuito_paralelismo` (N sesiones/worktrees simultáneas, clamp 1..12, default 6) →
  `RoadmapCircuitoService::getParalelismo()/setParalelismo()`
  (`app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php:2146`), setting runtime en
  tabla `settings`, editable sin redeploy desde `/releases?tab=configuracion`.
- `paralelo_mismo_modulo` (cuántos items del MISMO módulo pueden correr a la vez, default 1,
  hoy en 2 por decisión de #916) → `TorreConfig::paraleloMismoModulo()`
  (`app/Modules/Addons/Roadmap/Models/TorreConfig.php:104`), fila en `torre_config`, mismo
  panel de configuración.

## Aprobados crudos vs. elegibles reales para despacho

Confirmado con datos en vivo (2026-09-12):

| Métrica | Valor |
|---|---:|
| `estado_aprobacion IN aprobado_*` (crudo) | 194 |
| — de esos, paraguas parqueados (`excluir_pool_automatico=true`, nunca despachan) | 139 |
| `RoadmapItem::despachable()` (elegibles reales AHORA) | **27** |

El propio #9990863 ya había reportado "169+27" — el número que importa para diagnosticar "por
qué no asigna" es el de `scopeDespachable()` (27), no el crudo de `estado_aprobacion` (194): el
71% de los aprobados crudos son paraguas ya cerrados-por-descomposición que jamás iban a
despachar, no trabajo perdido. El panorama de la Torre debe leerse con esta distinción en mente.

## "pausado=0" conviviendo con "N sin modelo" — desambiguado

El item señala como confuso ver el kill switch en `pausado=0` (circuito corriendo normal) al
mismo tiempo que un contador "N escalaron SIN MODELO" en la Torre
(`TorreControl.vue:113`, badge `digest.sin_modelo`). Investigado: **son tres significados
distintos de "modelo" en el codebase**, y ninguno es un freno del propio despachador:

1. **`digest.sin_modelo` (Torre, badge junto al header de pausado)** — cuenta veredictos del
   **revisor de items** (`RevisorService::CATEGORIAS_SIN_JUICIO`, agregado en `DigestCommand`)
   donde la llamada a la API de Claude **nunca contestó** al triar un item nuevo (key rota, red
   caída, timeout). Es una falla-segura: el revisor escala en vez de trabarse, y el item cae en
   la bandeja de Irving pareciendo "prudencia" cuando en realidad es la IA caída (comentario
   ya existente en el código, `DigestCommand.php:284`, item #807). **No tiene relación con el
   despachador de items** (`SchedulerCommand`/`RoadmapCircuitoService`) — es un problema
   upstream, en la triage inicial, no en el reparto de trabajo ya aprobado.
2. **`--modelo=` en `deploy/circuito/vuelta.sh` (flags de arranque de cada vuelta)** — qué
   variante de modelo (Sonnet/Opus) ejecuta ESA vuelta puntual; se imprime en su propio log de
   arranque (`log "flags: pausado=... modo=... modelo=..."`), nada que ver con el badge de la
   Torre ni con el revisor.
3. **"Sin modelo" en `SmartImportExport`** (`SmartImportService.php`, `SmartImport.vue`) — dominio
   totalmente ajeno: tablas de BD sin modelo Eloquent asociado, para el importador. Coincidencia
   de texto, cero relación con el circuito.

`pausado=0` y `digest.sin_modelo` pueden convivir sin contradicción: **el kill switch gobierna
si el pool reparte trabajo**; `sin_modelo` mide **si el revisor pudo emitir juicio al triar**.
Son capas distintas — el circuito puede estar activo y repartiendo trabajo normalmente (0
pausado) mientras, en paralelo, algunos triajes recientes fallaron por caída de la API (>0
sin_modelo). No es una anomalía a corregir: es la señal de que la falla-segura funcionó (escaló
en vez de fallar en silencio), documentada aquí para que deje de leerse como contradictorio.

## Conclusión

Ningún código de configuración requiere cambio (C3/C4 no aplican — 0 evidencia). Item cerrado
como análisis/documentación completa: frecuencia real de los 15 códigos, ubicación de las 2
perillas de concurrencia (sin tocarlas, por falta de evidencia que lo justifique), distinción
aprobados-crudos vs. despachables reales con números en vivo, y desambiguación de las 3
"modelo"/"sin modelo" que convivían sin relación entre sí en el panorama de la Torre.

**Sin cambio de código de negocio** — es un item de análisis puro (nivel B, autorizado por el
revisor como "técnica, reversible, ajuste de parámetros operativos"; la decisión tomada fue
justamente NO ajustar ningún parámetro por falta de evidencia).
