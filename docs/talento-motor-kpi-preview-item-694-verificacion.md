# Item #694 — Talento comisión-KPI Fase B: motor de preview READ-ONLY (duplicado de #698, bloqueado)

## Contexto

El item #694 es un **duplicado** de #698: ambos piden la misma Fase B del plan C→B→A del
des-trabe de #645 — extender `LiquidationService` (o un servicio paralelo) para leer
`talento_compensation_rules` por `target_type`/`variable_type`/`kpi_key`/`formula_config` y
**calcular un monto simulado** (sin escribir en `talento_ledger_entries` ni afectar
`countBillableUnits()` real), expuesto como preview en `/talento/compensacion`.

El propio texto de #694 trae la misma condición de entrada explícita que #698:

> "Depende de que la Fase C (sub-item hermano) ya tenga respuestas de Irving: rol piloto + KPI +
> fórmula real + fuente de datos confirmada [...] NO implementar sin las respuestas de la Fase C
> — sin fórmula real, no hay qué calcular."

## Verificación de la dependencia (esta vuelta)

- `talento_compensation_rules` sigue en **0 filas** (verificado por query directa, tinker).
- La Fase C hermana (#693, duplicado de #697) sigue `estado_aprobacion=requiere_irving`: el
  catálogo/UI para cargar reglas ya existe (obra de #121, ver
  `docs/talento-comision-kpi-catalogo-item-693-verificacion.md`), pero nadie cargó todavía una
  regla real ni contestó las 4 preguntas de negocio (rol piloto, KPI/fórmula real, ventana de
  clawback, origen de datos de "recuperado cobranza"/"csat").
- Esta misma pregunta ya se resolvió, dos veces, para la cadena idéntica:
  - #698 (duplicado exacto de este item) → `docs/talento-motor-kpi-preview-item-698-verificacion.md`:
    consultado Thomas, respuesta **PROCEDE** con "documentar y dejar bloqueado, sin construir el
    motor" (inventar la estructura/DSL de `formula_config` sin datos de Irving es la misma
    frontera dura de dinero que bloqueó #645).
  - #700 (Fase A, un eslabón más arriba) → `docs/talento-motor-kpi-fase-a-item-700-verificacion.md`:
    aplicó el mismo criterio sin re-consultar, por ser la misma pregunta objetivamente verificada
    (`talento_compensation_rules` en 0 filas) sobre el mismo eslabón de la cadena.
- Un pico de escalación adicional quedó en el propio log de #694 (consulta de `wt-2`,
  2026-08-28 18:43): preguntó a Thomas si aplicaba el mismo criterio de #693 a #694; Thomas
  escaló a Irving. Irving volvió a marcar `aprobado_irving` sobre el item **después** de esa
  escalación, pero sin comentario ni respuesta a las 4 preguntas de negocio — la aprobación
  reautoriza que el circuito trabaje el item, no sustituye las respuestas que el propio texto de
  #694 exige antes de calcular nada.

## Por qué no se ejecuta el motor en esta vuelta

Sin filas reales en `talento_compensation_rules`, no hay ningún `formula_config` real que leer:
construir el evaluador de fórmulas ahora mismo obligaría a **inventar la estructura/DSL de
evaluación de KPI** (qué campos trae `formula_config`, cómo se calcula el porcentaje, cómo se
marca el clawback) sin datos confirmados por Irving — exactamente la frontera dura de dinero que
el propio item cita, y la misma que ya resolvió Thomas para #698 con la opción recomendada y
reversible: documentar y bloquear.

No fue necesaria una consulta nueva a Thomas: es la misma pregunta, sobre el mismo par de items
duplicados (#693↔#697 en Fase C, #694↔#698 en Fase B), con la misma precondición objetivamente
incumplida (verificada por query, no por criterio), ya resuelta por Thomas para el gemelo #698 en
esta misma cadena.

## Cierre de esta vuelta

- Sin cambio de código de aplicación: ningún motor de cálculo escrito; `LiquidationService` y
  `countBillableUnits()` siguen intactos, como exige el propio item.
- Este documento deja registrado que #694 es duplicado de #698 y que ambos comparten el mismo
  bloqueo — para que ninguna vuelta futura reintente construir el motor sin la precondición de
  Fase C resuelta, ni vuelva a re-consultar la misma pregunta ya resuelta dos veces en esta cadena.
- El item **permanece bloqueado** (`requiere_irving`), esperando que Irving cargue una regla real
  en `/talento/compensacion` o conteste las 4 preguntas de #693/#697. Cuando eso ocurra, la Fase B
  (#694 o #698) puede retomarse con datos reales en vez de estructura inventada.
