# Item #698 — Talento motor de cálculo KPI en modo READ-ONLY (bloqueado por dependencia sin cerrar)

## Contexto

El item #698 es la **Fase B** del plan C→B→A del des-trabe de #645. Pide construir un motor de
preview/simulación que calcule —sin aplicar movimientos en `talento_ledger_entries`— la comisión
estimada + flag de clawback para roles no-técnicos, leyendo `formula_config` y las fuentes de KPI
que la **Fase C (sub-item #697)** haya confirmado.

El propio texto del item trae la condición de entrada explícita:

> "Depende de que la Fase C (sub-item #697) tenga reglas reales cargadas en
> `talento_compensation_rules` con fuentes de KPI confirmadas por Irving [...] NO ejecutar esta
> fase si la Fase C no cerró con reglas reales — inventar `formula_config` sin datos de Irving es
> la misma frontera dura de dinero que bloqueó #645."

## Verificación de la dependencia (esta vuelta)

- `talento_compensation_rules` sigue en **0 filas** (verificado por query directa).
- El item #697 (Fase C) **no cerró con reglas reales**: quedó `estado_aprobacion=requiere_irving`
  (bloqueado), documentado como duplicado de #693 en
  `docs/talento-comision-kpi-catalogo-item-693-verificacion.md` — el catálogo/UI para cargar
  reglas ya existe (obra de #121), pero nadie cargó todavía una regla real ni contestó las 4
  preguntas de negocio (rol piloto, KPI/fórmula real, ventana de clawback, origen de datos de
  "recuperado cobranza"/"csat").
- De los 3 KPI de ejemplo del plan original, solo `activaciones_netas` (ventas) tiene fuente de
  datos verificada en el codebase (`sellers`/`transaction_sellers`, bridge item #123);
  `recuperado_cobranza` y `csat/tickets_cerrados` **no existen en ninguna tabla del repo**.

## Por qué no se ejecuta el motor en esta vuelta

Sin filas reales en `talento_compensation_rules`, no hay ningún `formula_config` real que leer:
construir el evaluador de fórmulas ahora mismo obligaría a **inventar la estructura/DSL de
evaluación de KPI** (qué campos trae `formula_config`, cómo se calcula el porcentaje, cómo se
dispara el clawback) sin datos confirmados por Irving — exactamente la frontera dura de dinero
que el propio item cita como equivalente a la que bloqueó #645.

Se consultó a Thomas en esta vuelta para confirmar el camino (no construir el motor, documentar y
dejar bloqueado esperando a que #697 cierre con reglas reales) — respuesta **PROCEDE** con
exactamente esa opción (recomendada por la propia terminal, fuera del conjunto de escalamiento).

## Cierre de esta vuelta

- Sin cambio de código de aplicación (ningún motor de cálculo escrito; nada que tocar en
  `LiquidationService::calculate()`/`countBillableUnits()`, que siguen intactos como exige el
  item).
- Este documento deja registrado que la Fase B **no puede avanzar** hasta que la Fase C (#697)
  cierre con reglas reales — para que ninguna vuelta futura reintente construir el motor sin esa
  precondición.
- El item **permanece bloqueado** (`requiere_irving`), esperando que Irving cargue una regla real
  en `/talento/compensacion` o conteste las 4 preguntas de #693/#697. Cuando eso ocurra, la Fase B
  puede retomarse con datos reales en vez de estructura inventada.
