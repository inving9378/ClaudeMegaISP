# Auditoría — 13 items `completado` sin `merge_commit` (item #9990061)

Medido 2026-09-03/04. Corresponde al item #9990061 de la Hoja de Ruta.

## 1. Listado verificado (BD + git, uno por uno)

Consulta base:

```sql
SELECT id, title, branch, nivel_riesgo, completed_at
FROM roadmap_items
WHERE estado_aprobacion = 'completado'
  AND branch IS NOT NULL AND merge_commit IS NULL AND archivado_at IS NULL
ORDER BY id;
```

Para cada uno se resolvió el hash de cabeza de rama y se corrió
`git merge-base --is-ancestor <hash> main` (equivalente a `git branch --contains <hash> --list main`).

| # | Título (resumen) | Cierre | Commits propios (fuera de main) | ¿Contenida en main? | Causa de cierre |
|---|---|---|---|---|---|
| 279 | Auto-merge de Thomas puede cerrar rama vieja | 2026-08-29 03:13 | **0** | Sí (branch = main) | Paraguas — cascada (`saved` hook) |
| 646 | Válvula de contexto: instrumentar | 2026-08-29 11:00 | **0** | Sí | Paraguas — cascada |
| 672 | Pieza 1 instrumentar válvula (contador) | 2026-08-29 11:00 | 1 (`docs/bitacora-sesiones.md` + `CLAUDE.md`) | **No** | Paraguas — cascada |
| 681 | [RESPUESTA] DocCorp Fase 0 — 6 desviaciones | 2026-08-28 22:49 | 1 (`docs/bitacora-sesiones.md` + `CLAUDE.md` + doc propio) | **No** | Cierre normal, merge intentado y **escalado** |
| 705 | Vigilante on-box #208 — invariantes Cola | 2026-09-03 13:26 | 1 (`docs/bitacora-sesiones.md` + `CLAUDE.md` + doc propio) | **No** | Paraguas anidado — cascada (tras ~25 reintentos fallidos de merge) |
| 739 | Deriva esquema #216 Fase 2 | 2026-08-29 15:21 | **0** | Sí | Paraguas — cascada |
| 740 | Deriva esquema #216 Fase 3 | 2026-09-01 14:13 | 1 (`docs/bitacora-sesiones.md` + `CLAUDE.md` + doc propio) | **No** | Paraguas — cascada |
| 797 | Deriva #216 Fase 1a | 2026-09-01 13:19 | **0** | Sí | Paraguas — cascada |
| 806 | Jarvis 3b — backend del chat | 2026-08-31 17:22 | 1 (**feat real**: 6 archivos, tablas + service + controller) | **No** | Cierre silencioso — sin traza de `circuito:integrar` |
| 825 | Jarvis 3b Fase 1 — migración | 2026-08-29 17:50 | 2 (feat + su propio revert; **diff neto = 0**) | **No** | Cierre silencioso — sin traza de `circuito:integrar` |
| 900 | Auditoría #875 Detector B null-safety | 2026-09-03 17:29 | **0** | Sí | Paraguas — cascada |
| 933 | Versiones a la carta (armado de versión) | 2026-09-03 14:59 | **0** | Sí | Paraguas — cascada |
| 971 | MR-32 — liberador en cascada MAPA DE RED | 2026-09-03 14:52 | 1 (**feat real**: comando + Kernel + ModuleServiceProvider) | **No** | Cierre por tinker; merge intentado 5h después → **CONFLICTO real** |

**Resumen:** 6 de 13 no tienen nada que perder (rama = main, cero commits propios — la
investigación quedó documentada solo en los campos de BD, `reporte_coloquial`/`comentarios_claude`,
nunca en un commit). Los otros 7 sí tienen trabajo real varado: 5 son notas `docs/` de cierre de
paraguas (bajo impacto) y **2 son código funcional sin mergear** (#806 backend de chat Jarvis,
#971 el liberador de la épica MAPA DE RED — el que el propio item señala como bloqueante).

## 2. Causa raíz — dos rutas de cierre que nunca llaman al merge

Se revisó `app/Modules/Addons/Roadmap/Models/RoadmapItem.php` (hook `saved`, bloque de cierre en
cascada), `JarvisService::pendienteReal()`/`enqueueMerge()`, `IntegrarItemCommand`,
`RoadmapController::integracionMerge()` (botón manual) y `MergeRunner::drain()`/`SchedulerCommand`.

### Ruta A — cierre en cascada de paraguas (mayoría: 8 de 13)

`RoadmapItem.php:472-501` (`static::saved`): cuando el último sub-item de un paraguas cierra, el
hook hace directamente:

```php
$padre->estado_aprobacion = 'completado';
$padre->save();
```

**Nunca llama `JarvisService::enqueueMerge()`.** Si el padre tenía su propio commit (por ejemplo la
nota donde el ejecutor documentó la decisión de descomponer, antes de crear los sub-items), ese
commit queda huérfano para siempre — nada vuelve a mirarlo. Afecta a #279, #646, #672, #705, #739,
#740, #797, #900, #933 (el mismo patrón, ya visto y corregido caso por caso en items previos como
#738/#745/#830/#816/#818/#848/#878/#905/#906/#907/#924, documentados en CLAUDE.md — pero **nunca se
tocó la causa estructural**: el hook en sí sigue sin encolar merge).

### Ruta B — cierre manual sin pasar por `circuito:integrar` (#806, #825, #971)

El flujo correcto (el que sigue este mismo ejecutor, ver su propio prompt paso 6) es: primero
`circuito:integrar <id>` (encola el merge), y **luego** el `tinker` que fija
`estado_aprobacion = 'completado'`. #971 muestra el flag `"por":"consola:tinker"` cerrando el item
directo a `completado` sin ese paso — su merge sólo se intentó **5 horas después**
(2026-09-03 19:50:04), casi seguro por el botón manual de Irving en la Torre
(`RoadmapController::integracionMerge`, `POST /api/roadmap/integracion/merge`), no por nada
automático. #806 y #825 no dejan ni ese rastro tardío: cero eventos `merge-runner` en su log — su
merge **nunca se encoló, ni entonces ni después**.

### El archivo caliente: `docs/bitacora-sesiones.md`

Los 7 commits huérfanos (100%, sin excepción) tocan `docs/bitacora-sesiones.md` — es la
`REGLA PERMANENTE` de `CLAUDE.md`: todo cierre de tarea grande debe *appendear* ahí. Con N
terminales corriendo en paralelo (este mismo circuito), es el archivo más disputado del repo:

- **10 de los eventos `merge_escalado` de #705** fallan con el mismo motivo: *"El checkout
  principal tiene cambios sin commitear en archivos que ESTE merge toca
  (docs/bitacora-sesiones.md)"* — el árbol de `/var/www/megaisp` (donde corre `MergeRunner`) tenía
  ediciones locales sin commitear sobre ese archivo, y el runner aborta por diseño en vez de
  arriesgar perder cambios. Tras 3 escalaciones seguidas por la misma causa, el propio circuito lo
  marcó `bloqueado_por_bucle=true` + `excluir_pool_automatico=true` — sacándolo de cualquier reintento
  automático futuro. Se quedó así hasta que la cascada lo completó igual, meses… horas después.
- El intento de #971 (5h después del cierre) topó con un **conflicto de contenido real** (no solo
  árbol sucio): dos ramas insertando texto al final del mismo archivo casi al mismo tiempo. Ese sí
  requiere resolución humana/agente — no es un bug del runner, es la naturaleza de un log
  append-only editado por muchas ramas paralelas.

### `circuito:destrabar-bandeja` (#566) — confirmado NO agendado, y NO habría ayudado aquí

`crontab -l | grep destrabar-bandeja` → 0 coincidencias (confirmado). Existe una línea de cron para
`circuito:destrabe` (cada 4 min) que **es un comando totalmente distinto** (`DestrabeCommand.php`,
re-triage de la bandeja `requiere_irving` con Opus) — nombre parecido, función distinta; fácil de
confundir y probablemente la razón por la que nadie notó que `destrabar-bandeja` faltaba.

Pero aunque se hubiera agendado, **no habría movido ninguno de estos 13 items**:
`JarvisService::pendienteReal()` (línea 932-934) devuelve `'cierre'` — *"El item ya está cerrado o
archivado"* — para cualquier item con `estado_aprobacion` en `['completado','cancelado','rechazado']`,
ANTES de siquiera llegar a la rama de decisión `'merge'` (línea 939 en adelante). El comando fue
diseñado para items **todavía no completados** cuyo único pendiente es la aprobación/merge de
Irving (`aprobado_irving`/`esperando_merge_irving`) — una población real y separada, que **si**
vale la pena atender agendando el comando, pero es otro problema distinto al de este item.

## 3. Recomendación sobre el cron

- **Sí, agregar `circuito:destrabar-bandeja --apply` al crontab** — atiende una bolsa real (items
  `aprobado_irving`/`esperando_merge_irving` con trabajo listo esperando solo el merge), que hoy
  sólo se drena si alguien lo corre a mano. Frecuencia sugerida: **cada 10 minutos**
  (`*/10 * * * *`), igual que `circuito:reactivar-agendados` — es más caro que un tick de
  `circuito:scheduler` (evalúa política de auto-merge/auto-decisión, no un simple drain) pero no
  tan raro como para dejar la bandeja horas sin tocar. Usar `--cap` de config normal (no el
  override de destrabe inicial).
- Esto **no** resuelve los 13 items de esta auditoría — ver fix mínimo abajo.

## 4. Fix mínimo propuesto (no aplicado en este item — es una decisión de diseño sobre un mecanismo compartido)

**Ampliar el barrido, no el hook.** Tocar el hook de cascada (`RoadmapItem.php:472-501`) para que
llame `enqueueMerge()` en medio de un `static::saved` es riesgoso (reentrancia sobre el mismo save,
recursión padre→abuelo). Más seguro y aditivo: agregar una consulta explícita —ya sea como nueva
opción de `circuito:destrabar-bandeja` o un comando dedicado `circuito:reintentar-merges-completados`,
agendado cada 10-15 min— que busque exactamente:

```sql
estado_aprobacion = 'completado' AND branch IS NOT NULL AND merge_commit IS NULL AND archivado_at IS NULL
```

y llame `enqueueMerge($item->id, 'auto-completado-huerfano', 'auto')` por cada uno (reusando
`MergeRunner::performMerge`, que ya es idempotente: si la rama resulta ser ancestro de `main` — como
6 de los 13 — sólo rellena `merge_commit` sin tocar nada). Con esto:

- Los 6 items sin commits propios (#279/#646/#739/#797/#900/#933) quedan con `merge_commit`
  poblado de inmediato (no-op idempotente de `performMerge`), cerrando el hueco de tracking.
- Los 5 con solo notas `docs/` (#672/#681/#705/#740 + el revert #825) se integran solos o, si
  chocan con `docs/bitacora-sesiones.md`, vuelven a escalar — pero esta vez **quedan visibles en un
  barrido periódico real**, no enterrados para siempre por el bloqueo anti-bucle de una cascada que
  ya no vuelve a tocarlos.
- Los 2 con código real (#806, #971) por fin se encolan; #971 ya sabemos que topa con conflicto de
  contenido — quedará en `requiere_irving` con el error visible en la Torre, que es el
  comportamiento correcto (un conflicto de contenido real necesita ojos, automatizarlo de más sería
  peligroso).

**Raíz estructural aparte (fuera de alcance de un fix mecánico):** mientras `docs/bitacora-sesiones.md`
siga siendo un archivo único que TODA rama debe tocar al cerrar, seguirá siendo el punto de choque
más disputado del repo bajo N terminales en paralelo. No se propone tocar esa convención aquí (es
decisión de Irving, y tiene su propio trade-off: perder el archivo único rompe "todo el relato en un
solo lugar cronológico"); se deja anotado como la causa de fondo detrás de casi todos los conflictos
de merge observados en esta auditoría.

## 5. Alcance de esta auditoría

100% de solo-lectura: lectura de código, consultas `SELECT`, comandos git de solo lectura
(`rev-parse`, `merge-base --is-ancestor`, `log --not`, `show --stat`) y revisión de `crontab -l`.
**No se mergeó nada.** Los 13 items siguen exactamente como estaban; la decisión de qué hacer con
cada uno (mergear #806/#971 en particular) sigue siendo de Irving desde la Torre.
