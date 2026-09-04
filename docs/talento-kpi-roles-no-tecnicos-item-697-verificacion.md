# Item #697 — Talento: catálogo de reglas KPI para roles no-técnicos (RESUELTO — duplicado de #693)

## Contexto

El item #697 nace como sub-item de seguimiento de #645 (auditoría del motor de compensación de
Talento), creado el 2026-08-28 17:03. Pide fijar en `talento_compensation_rules` las reglas
reales por rol no-técnico: (1) qué roles no-técnicos entran, (2) qué KPI y `formula_config`
aplica a cada uno, (3) ventana/disparadores de clawback con números reales, (4) módulo piloto.
Su propio texto es explícito: **"Esto es una decision de negocio de Irving, no de
implementacion: sin porcentajes/tabuladores reales el circuito no puede avanzar sin inventar
reglas de dinero."**

## Hallazgo — es el mismo pedido que el item #693, ya resuelto

El item **#693** ("Talento comisión-KPI — Fase C: catálogo de reglas (Irving debe fijar
roles/KPIs/fórmula/piloto antes de código)") es **el mismo sub-item de #645**, creado el mismo
día, con idéntico alcance (las mismas 4 preguntas: rol/módulo piloto, KPI+fórmula real,
ventana de clawback, origen de los KPI que no existen en el codebase). #693 ya se investigó,
se consultó a Thomas (respuesta **PROCEDE** con "no inventar valores, documentar y dejar
bloqueado") y se cerró documentando en
`docs/talento-comision-kpi-catalogo-item-693-verificacion.md` (commits `3c350c87`+`6fa9ea62`,
ya en `main`). Esa vuelta confirmó punto por punto lo que el propio #697 pide verificar:

- El **catálogo/scaffolding ya existe**, construido por el item #121 (cerrado 2026-08-27,
  commits `f7508751`+`ffc5ea32`): migración con `variable_type`, `kpi_key`, `formula_config`,
  `valid_from`/`valid_until`, `monthly_cutoff_day`, `clawback_days`,
  `clawback_requires_collection` + `target_type` ampliado a `accounting`/`support`; modelo
  `TalentoCompensationRule` con los 8 campos en `$fillable`+`$casts`; controller
  `TalentoCompensacionController::storeRule`/`updateRule` validándolos; UI Vue
  `TalentoCompensacion.vue` con el modal de alta/edición ya exponiendo los 8 campos. **No hay
  UI ni schema por construir** — la herramienta para que Irving cargue una regla real ya está
  operativa en `/talento/compensacion`.
- `talento_compensation_rules` sigue en **0 filas** (verificado de nuevo en esta vuelta,
  2026-08-29): nadie cargó todavía una regla real.
- La aprobación de Irving sobre #697 (`estado_aprobacion=aprobado_irving`, log
  `irving:admin` 2026-08-28 18:30, `comentario: null`, `respuestas: []`) autorizó que el
  circuito trabajara el item, pero — igual que pasó con #693 — **no sustituye las respuestas de
  negocio** que el propio item exige (roles, KPI/fórmula/porcentajes reales, ventana de
  clawback). Sin esas respuestas no hay ningún valor legítimo que un ejecutor pueda escribir:
  inventar un KPI o un porcentaje de comisión fijaría una regla de dinero real sin
  autorización — la misma frontera dura (gastar dinero) que Thomas ya confirmó para #645/#693.

## Por qué no se re-consultó a Thomas en esta vuelta

La pregunta y la respuesta ya están resueltas de forma idéntica para el mismo alcance (mismo
padre #645, mismas 4 preguntas, mismo bloqueo de negocio) en la vuelta de #693. Re-consultar
sería repetir una pregunta ya contestada. Se aplica aquí la misma resolución ya autorizada:
**no inventar valores, documentar, dejar bloqueado esperando a Irving.**

## Cierre de esta vuelta

- Sin cambio de código de aplicación (el catálogo ya estaba completo desde #121; nada que
  construir de nuevo).
- Este documento deja registrado que #697 es el mismo pedido que #693 (ya resuelto), para que
  ninguna vuelta futura los re-investigue por separado.
- El item permanece bloqueado, esperando que Irving conteste las 4 preguntas — por texto en el
  item #693/#697, o cargando directamente la regla real en `/talento/compensacion` (la UI ya lo
  permite, sin necesidad de pasar por el circuito).
