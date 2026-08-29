# Item #693 — Talento comisión-KPI Fase C: catálogo de reglas (bloqueado por decisión de negocio)

## Contexto

El item #693 nace como sub-item de seguimiento de #645 (auditoría exhaustiva del motor de
compensación de Talento). Su propio texto es explícito: **"No es código: es la decisión de
negocio que bloquea todo lo demás."** Pide que Irving conteste 4 preguntas antes de escribir
cualquier línea del motor de comisión-KPI:

1. Qué rol no-técnico (contabilidad/mostrador/atención a clientes) entra primero como piloto.
2. Para ese rol, el KPI exacto y la fórmula/tabulador real (porcentajes reales, no inventados).
3. Ventana y disparadores de clawback (días de gracia, si requiere cobro).
4. Si "recuperado cobranza" o "csat" entran al piloto, de dónde salen esos datos (hoy no
   existen en ningún lado del codebase) o excluirlos del alcance inicial.

## Qué ya existe (obra del item #121, previo a #693)

Verificado en esta vuelta que el **catálogo/scaffolding que el título de #693 nombra
("Fase C: catálogo de reglas") ya está construido**, por un item anterior y distinto (#121,
"[DECISIÓN] Talento: definir reglas de compensación de roles no-técnicos", cerrado
2026-08-27, commits `f7508751`+`ffc5ea32`):

- **Migración** `2026_08_27_200410_add_kpi_compensation_fields_to_talento_compensation_rules.php`
  — amplía `target_type` a `accounting`/`support` (además de `technician`/`seller`/`counter`/`all`
  ya existentes) y agrega columnas `variable_type`, `kpi_key`, `formula_config` (json),
  `valid_from`, `valid_until`, `monthly_cutoff_day`, `clawback_days`,
  `clawback_requires_collection` — todas nullable, **sin ningún valor precargado**.
- **Modelo** `TalentoCompensationRule` — los 8 campos nuevos en `$fillable`+`$casts`.
- **Controller** `TalentoCompensacionController::storeRule`/`updateRule` — valida y acepta
  los 8 campos nuevos (`nullable`), incluye `target_type` con `accounting`/`support`.
- **UI Vue** `resources/js/components/module/talento/TalentoCompensacion.vue` — el modal de
  alta/edición de regla ya expone selects/inputs para los 8 campos (KPI key, variable_type,
  vigencia, corte mensual, clawback y su checkbox de "requiere cobranza"), deshabilitados hasta
  que se elige un `variable_type`.

Es decir: **el "catálogo de reglas" (la herramienta para que Irving cargue una regla real) ya
existe y está operativo en `/talento/compensacion`.** No hay UI ni schema por construir.

## Qué falta (y por qué no se puede resolver desde código)

`talento_compensation_rules` sigue en **0 filas** — nadie cargó todavía una regla real con
KPI/fórmula/piloto. El log de aprobación de #693 (`estado_aprobacion=aprobado_irving`,
2026-08-28 18:30) no trae texto de respuesta a las 4 preguntas (`comentario: null`,
`respuestas: []`) — la aprobación autorizó que el circuito trabajara el item, pero no sustituye
las respuestas de negocio que el propio item exige.

Sin esas respuestas (rol piloto + KPI + fórmula/porcentajes reales + ventana de clawback), no
hay ningún valor legítimo que un ejecutor pueda escribir: inventar un KPI o un porcentaje de
comisión sería fijar una regla de dinero real sin autorización — la misma frontera dura que el
propio item cita como "confirmada con Thomas en #645" (y que #121 confirmó de forma
independiente: ver su consulta `wt-5 2026-08-26 15:06`, escalada a Irving por Thomas con el
mismo motivo).

Se consultó a Thomas en esta vuelta para confirmar el camino (no inventar valores, documentar y
dejar bloqueado) — respuesta **PROCEDE** con exactamente esa opción.

## Cierre de esta vuelta

- Sin cambio de código de aplicación (el catálogo ya estaba completo desde #121).
- Este documento deja registrado que la parte de "catálogo" del título de #693 ya está resuelta,
  para que ninguna vuelta futura la re-investigue o la re-construya.
- El item **permanece bloqueado**, esperando que Irving conteste las 4 preguntas (por texto en
  el item, o cargando directamente la regla real en `/talento/compensacion` si prefiere saltarse
  el texto e ir directo a los datos — la UI ya lo permite).
