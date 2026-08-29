# Item #695 — Talento comisión-KPI Fase A: duplicado de #700, misma cadena sin cerrar

## Contexto

`#695` es un sub-item hermano de `#700` — ambos son la **misma Fase A** ("motor activo + evaluación
real de clawback") del des-trabe C→B→A del item padre `#645`. La descripción de `#695` es
prácticamente idéntica a la de `#700`: enganchar `countBillableUnits`/`calculate` al pago real vía
`talento_ledger_entries` y activar la evaluación real de clawback (`clawback_days`,
`clawback_requires_collection`), y trae la misma condición explícita de entrada:

> "Depende de que la Fase B (sub-item hermano) esté validada en preview por Irving contra datos
> reales [...] Es la fase que sí mueve dinero real (frontera dura) — requiere sesión supervisada
> con Irving, no ejecutar en automático aunque nivel_riesgo lo permita."

Una vuelta anterior de este mismo worktree (`wt-5`) ya consultó a Thomas sobre `#695` con estas
mismas dos opciones (shadow / no ejecutar) y Thomas escaló a Irving por no traer ninguna opción
reversible. Irving aprobó el item (`aprobado_irving`, dos veces) desde el roadmap — ese aprobado es
el gate de nivel C que habilita que el item se trabaje, no una instrucción de saltarse la
precondición que el propio texto del item exige.

## Verificación de la cadena de dependencias (esta vuelta)

Re-verificado en esta vuelta, con query directa (no por criterio):

1. **Fase C — catálogo de reglas** (`#693`/`#697`, duplicados): `#693` sigue en
   `requiere_irving`. `talento_compensation_rules` sigue en **0 filas** — nadie cargó una regla
   real ni contestó las 4 preguntas de negocio pendientes (rol piloto, KPI/fórmula real, ventana de
   clawback, origen de datos).
2. **Fase B — motor de preview READ-ONLY** (`#698`/`#694`, duplicados): ambos cerrados como
   `completado` documentando que el preview **nunca se construyó** — sin filas reales en
   `talento_compensation_rules` no hay `formula_config` que leer.
3. **Fase A — motor activo + clawback real** (`#700`, gemelo exacto de este item): ya investigado y
   cerrado como `completado` en `docs/talento-motor-kpi-fase-a-item-700-verificacion.md`, con la
   misma conclusión: la precondición ("Fase B validada por Irving con datos reales") no solo no está
   validada — Fase B ni siquiera llegó a construirse.
4. `LiquidationService::calculate()`/`countBillableUnits()` (grepeado en esta vuelta): solo consumen
   `TalentoCompensationRuleHistory` (snapshot histórico de motor de cuota/unidad de técnicos), **sin
   ninguna referencia** a `talento_compensation_rules`/KPI variable. Intacto, sin regresión.

Nada cambió desde que se cerró `#700`: los 3 eslabones de la cadena siguen exactamente en el mismo
punto.

## Por qué no se ejecuta el motor en esta vuelta

Construir el motor activo de pago + clawback ahora requeriría inventar todo lo que Fase B ya se
negó a construir (estructura de `formula_config`, fórmula real de KPI, fuente de datos de
"recuperado cobranza"/"csat") más la pieza propia de Fase A que el item marca como indefinida:
"qué cuenta como 'cliente activado por este vendedor/agente' y cómo se liga la comisión pagada a
ese cliente para poder revertirla". Ninguna definición existe todavía en el repo ni en una
respuesta de Irving sobre estos puntos. Escribir el motor implicaría inventar la lógica de negocio
que decide cuánto se paga y cuándo se revierte dinero real de comisiones — la frontera dura exacta
que el propio item, el des-trabe (Opus) y la consulta previa a Thomas señalaron.

No fue necesaria una consulta nueva a Thomas: es la misma pregunta que ya resolvió para el eslabón
inmediato anterior (`#698`, "PROCEDE con documentar y bloquear, sin escribir código de cálculo") y
para el gemelo exacto de este item (`#700`, misma conclusión), con la misma precondición
objetivamente incumplida. Tratar `#695` distinto a `#700` — su duplicado exacto en la misma cadena,
verificado el mismo día — no tendría justificación.

## Cierre de esta vuelta

- Sin cambio de código de aplicación: ningún motor de pago/clawback escrito, `LiquidationService` y
  el ciclo de liquidación real intactos, exactamente como exige el propio item.
- Este documento deja registrada la re-verificación de la cadena C→B→A para el duplicado `#695` y
  referencia el precedente `#700`.
- El item permanece bloqueado en el fondo (la decisión de negocio sigue sin llegar), aunque se
  cierra como `completado` en el roadmap por ser trabajo de investigación ya agotado — igual que su
  gemelo `#700`. Cuando Irving cargue reglas reales en `/talento/compensacion`, el orden correcto
  sigue siendo Fase C → Fase B → Fase A (con aprobación explícita adicional para mover dinero real
  en cada entorno).
