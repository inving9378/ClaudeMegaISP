# Item #1004 — verificación §1 (disparo por hambre) + cierre del resto de la directiva

Item #1004 pedía, ANTES de construir nada, verificar tres cosas sobre "el disparo por
ociosidad del auditor". Esta es esa verificación, con evidencia citada, más el estado real
del resto de la directiva (§2-§5) al momento de ejecutar este item.

## §1 — Verificación (read-only)

**1. ¿Hay disparo por ociosidad, o el auditor solo corre por tiempo?**

Existe disparo por **hambre** (cola de items reclamables baja el umbral), no solo por tiempo.
`AuditorService::debeCorrer()` (`app/Modules/Addons/Roadmap/Services/AuditorService.php`) se
llama en cada vuelta de `circuito:scheduler` (`SchedulerCommand.php` ~L106-116) y sólo deja
correr un ciclo si `profundidadCola() < umbral` (default 3, `config('circuito.auditor.umbral_cola')`)
**y** ya pasó el intervalo mínimo desde la última corrida. Es decir: el *chequeo* es por tiempo
(cada minuto, enganchado al scheduler — no hay cron aparte, a propósito, ver docblock línea
~100 "una línea de cron aparte abriría una segunda carrera"), pero la *decisión de generar
trabajo* es por hambre (cola corta), exactamente lo que pedía §3: *"si items_reclamables < 3 y
no hay auditoría en curso → se encola una"*.

"Uno solo vivo a la vez" está garantizado por construcción, no por un lock explícito: el
scheduler es el único despachador (#432 B1) y corre el ciclo del auditor de forma síncrona
dentro de su propio proceso — no hay dos scheduler corriendo a la vez, así que no hay dos
auditorías corriendo a la vez.

Verificado en vivo (esta sesión):
```
habilitado: true · profundidadCola: 4 · slotsLibres: 3 · rachaSeca: 3
debeCorrer: {"corre":false,"motivo":"Cola con 4 item(s) reclamables (umbral 3): hay trabajo, no hace falta generar."}
```

**2. ¿Cuándo disparó por última vez y qué produjo?**

Última corrida: `2026-08-21T17:34:51-06:00` (dry-run, cap 10). **0 candidatos, 0 creados** en
los ~20 módulos escaneados — todos con `nuevos:0` (o `en_dod:true`, módulos ya completos). Esto
coincide al dígito con el diagnóstico que el propio prompt de #1004 traía en su tabla del §4:
*"Detectores mecánicos sobre el código: agotada, `Nuevos = 0`"*. `rachaSeca()` = 3 corridas
completas seguidas sin hallazgos → el freno por sequía (#1015, ver abajo) ya está alargando el
intervalo entre corridas en vez de seguir escaneando en vacío.

**3. Si no existe (parcialmente): ¿quedó registrado como item, o se dijo y nunca se convirtió?**

El mecanismo de disparo por hambre en sí **sí se convirtió en item e implementación**: nació
como **#559** ("Item Madre: Motor de auditoría continua", 2026-08-08) y se endureció con
**#1015** ("memoria de cobertura por módulo + freno por sequía", integrado a main). Lo que
Irving recordaba pedido-y-nunca-hecho no era el disparo por hambre del propio auditor (eso ya
estaba resuelto antes de escribir #1004) — era, específicamente, la regla de proceso del §2 y
la veta del §4 (minar la bitácora), que hasta este item no tenían ni un sub-item que las
seguntara. Ese es el gap real que #1004 vino a cerrar, y ya se cerró (ver §2/§4 abajo).

## §2 — Regla de proceso ("directiva → items antes de ejecutar")

Ya aplicada, retroactivamente, sobre esta misma directiva por una sesión anterior de este
mismo item (ver `comentarios_claude` de #1004): se dieron de alta **#1015** (resto del §3:
memoria de cobertura + freno por sequía — ya integrado a main) y **#1016** (señales de minería
restantes del §4, pendiente, dueño de otra vuelta). No se abrió sub-item para el §5
(enriquecimiento de `module.json`) porque el propio prompt indica que ya lo empuja el shell
móvil por su lado — correcto, fuera de alcance de #1004.

## §3 — Disparo por hambre "bien hecho"

Los tres topes que pedía el prompt ya están implementados en `AuditorService`:
- **Uno solo vivo a la vez** — por construcción (scheduler único, síncrono).
- **Memoria de cobertura** (`circuito_auditor_cobertura_modulos`, #1015) — rotación por módulo
  priorizando el nunca-auditado y luego el de auditoría más vieja (`modulosAAuditar()`).
- **Freno por sequía** (`circuito_auditor_racha_seca`, #1015) — backoff lineal del intervalo
  cuando N corridas seguidas dan 0 nuevos (`intervaloEfectivo()`), con techo configurable.

Nada pendiente de este numeral: ya cerrado por #559 + #1015 (ambos `completado`/integrados a
`main` antes de que esta vuelta arrancara).

## §4 — Minero de bitácora

Construido (miner v1, 2 de 5 señales: `opciones_sin_responder` + `referencia_rota`) y **corrido
en real** (`--apply`, no sólo dry-run) por una sesión anterior de #1004: produjo los items reales
**#1013** (13 casos, `requiere_irving` sin resolver — pendiente en bandeja de Irving) y **#1014**
(6 referencias `#NNN` rotas — investigado y cerrado, ver
`docs/roadmap-referencias-rotas-item-1014.md`). Las 3 señales restantes (`pendiente/falta` por
substring se descartó por 50% de ruido medido; las de "reporte del ejecutor" y "escalación sin
resolución" quedaron sin construir) se registraron como **#1016**, dueño de otra vuelta — no se
duplica aquí.

## §5 — Enriquecimiento de `module.json`

Explícitamente fuera de alcance de #1004 (el propio prompt: "que el shell móvil ya empuja por su
lado"). Sin acción de este item.

## Veredicto de cierre

Todo lo que #1004 pedía construir (§2 la regla de proceso, §3 el disparo por hambre bien hecho,
§4 el arranque del minero) ya está hecho — parte de antes (§559), parte por esta misma
directiva en una vuelta previa (#1015/#1016/#1013/#1014). Lo único que faltaba entregar era esta
verificación read-only del §1, que es el contenido de este documento. No queda código por
escribir bajo #1004 mismo: el trabajo vivo sigue en #1013 (bandeja de Irving) y #1016 (dueño de
otra vuelta), ambos ya rastreados como items independientes.
