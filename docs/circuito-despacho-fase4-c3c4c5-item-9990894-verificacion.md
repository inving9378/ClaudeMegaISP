# Circuito CC — Fase 4 (C3/C4/C5): frecuencias reales del log de despacho — item #9990894 / #9991069

## Contexto

C3/C4/C5 pedían decidir si había que subir los topes de paralelismo del circuito
(`paraleloMismoModulo()`/`getParalelismo()`) a partir de evidencia real de qué está frenando el
despacho, y aclarar la confusión entre el kill switch `circuito_pausado` y la métrica
`digest.sin_modelo` del widget Autopilot en la Torre.

## Ventana medida

`storage/logs/circuito-despacho-2026-09-11.log` + `circuito-despacho-2026-09-12.log` del checkout
principal (`/var/www/megaisp/storage/logs/`, único lugar donde corre el scheduler real). Ventana
real de ~17h20m (2026-09-11 23:46 a 2026-09-12 17:06), 5957 líneas `"wt-K ociosa: ..."`.

## Conteo completo de códigos (grep -oP, suma exacta del total de líneas)

| Código | Conteo | % |
|---|---|---|
| `dependencia_sin_cerrar` | 3017 | 50.6% |
| `bloqueado_por_dependencia` | 1654 | 27.8% |
| `desarrollo_humano` | 1286 | 21.6% |
| `footprint_desconocido` | 0 | 0% |
| `tope_modulo` | 0 | 0% |
| `sin_candidatos` | 0 | 0% |

Los demás códigos que `motivoNoDespachable()`/`diagnosticoCeroDespacho()` pueden emitir
(`ya_cerrado`, `descartado`, `freno_humano`, `esperando_merge`, `agendado`,
`bloqueado_por_bucle`, `sesion_supervisada`, `fuera_del_pool`, `en_progreso`, `no_despachable`)
tampoco aparecieron ni una vez en la ventana real.

## Conclusión sobre concurrencia (C3/C4) — NO subir paralelismo

`footprint_desconocido`/`tope_modulo` (las causas C3/C4) **no dominan** — de hecho, 0
apariciones reales en ~18h de operación. No hay evidencia para subir los topes de paralelismo.

Además, ya están subidos deliberadamente por el item #916 y su familia:
- `paraleloMismoModulo()` en runtime devuelve **4** (vía `TorreConfigService`, override en tabla
  `settings` editable en `/releases?tab=configuracion` — el default de `config/circuito.php:236`
  es 1, el env `CIRCUITO_PARALELO_MISMO_MODULO=2`, pero el override en BD ya lo subió a 4).
- `getParalelismo()` devuelve **6** (`config/circuito.php:214` `paralelismo`, env
  `CIRCUITO_PARALELISMO`).

**No tocar estos valores** — la propia regla del item ("no subir a ciegas") se cumple
absteniéndose, porque el dato real dice que ya están donde deben.

El cuello de botella real (`dependencia_sin_cerrar` + `bloqueado_por_dependencia` = 78.4%,
`desarrollo_humano` = 21.6%) es **estructural** (cadenas de dependencias/paraguas sin cerrar +
items marcados `en_desarrollo_humano`) — fuera del alcance de C3/C4/C5 (que solo pedían
concurrencia/frenos); no hay acción de config que lo resuelva.

## Aprobados vs. despachables reales

Medido en tinker, scope real `RoadmapItem::despachable()`:
- `estado_aprobacion IN (aprobado_claude, aprobado_revisor, aprobado_irving)`: **197**
- `RoadmapItem::despachable()` (elegibles reales): **28**
- De los 197, `excluir_pool_automatico=true` (paraguas parqueados): **140**

Confirma la premisa: el número que importa para diagnosticar "por qué no asigna" es **28**, no
197 — la diferencia son paraguas ya cerrados en cascada que nunca iban a despachar.

## `pausado=0` conviviendo con N items sin modelo — dos subsistemas distintos, no una contradicción

Widget Autopilot, `resources/js/components/module/releases/torre-control/TorreControl.vue`:

- `pausado` (línea 85, clase CSS `tc-ap-pausa`) = el kill switch `circuito_pausado`
  (`RoadmapCircuitoService::isPaused()`) — frena el **despacho** de nuevo trabajo a terminales.
- `digest.sin_modelo` (línea 112-114, tooltip `sinModeloTitle`, ~línea 867) = métrica de
  `circuito:digest` (`DigestCommand::escalacionesPorMotivo()`, tabla `circuito_revisiones`) —
  cuántas escalaciones del **revisor** (`RevisorService`, que hace su propia llamada a Claude para
  triar items, categoría `RevisorService::CATEGORIAS_SIN_JUICIO`) en los últimos N días fueron
  porque esa llamada a la API falló (key/red), NO por juicio real.

Verificado con datos reales del 2026-09-12: `pausado=false` y `sin_modelo=17` (últimos 7 días)
coexisten ahora mismo — la "convivencia confusa" es real, no hipotética.

Son **dos subsistemas distintos** que no se contradicen: `pausado` = si el despachador está
tomando trabajo nuevo (frenos reales: los códigos de `circuito_despacho` de arriba). `sin_modelo`
= si la IA que usa el revisor para clasificar items respondió — un canal de llamada API
completamente distinto al de `vuelta.sh`/CLI de Claude que ejecuta el trabajo (ese vive en
`modelo` del JSON de registro de `vuelta.sh`, `RegistrarEjecucionCommand`, sin relación). Ninguno
de los dos es un "freno del despachador" en el sentido de `circuito_despacho`.

## Cambio aplicado (Fase 4b)

Tooltip `sinModeloTitle` (`TorreControl.vue`, ~línea 867-875) ampliado con un párrafo que
explicita esta distinción: que `sin_modelo` es independiente de que el circuito esté pausado o no
y que no es un freno del despachador — es la llamada API propia del revisor. Cambio de texto puro,
sin lógica nueva.

## Referencia

Item raíz de esta cadena de seguimiento: #9990894 (C3/C4/C5) → #9991069 (Fase 4b) →
sub-item de ejecución (mismo #9991069, ejecutado directo por límite de profundidad de sub-items).
