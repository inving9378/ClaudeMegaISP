# Item #700 — Talento: activar motor de comisión-KPI + clawback real (bloqueado por dependencia sin cerrar)

## Contexto

El item #700 es la **Fase A** del plan C→B→A del des-trabe de #645: activar en Talento el motor
de comisión-KPI con **pago real** (vía `talento_ledger_entries`) y **clawback real** (reversión de
comisiones si el cliente se da de baja antes de `clawback_days`, o si `clawback_requires_collection`
y cobranza no cobró el mes). Toca dinero real — frontera dura que el propio item marca como
"requiere aprobación explícita de Irving antes de activarse en cualquier entorno, incluido dev con
datos reales".

El propio texto del item trae, además de la aprobación de Irving a nivel roadmap, una condición de
entrada explícita:

> "Depende de que la Fase B (sub-item de preview READ-ONLY) haya sido validada contra datos reales
> por Irving [...] NO ejecutar si la Fase B no fue validada primero."

## Verificación de la cadena de dependencias (esta vuelta)

La cadena completa C→B→A tiene 3 eslabones, y **ninguno de los dos anteriores a A cerró con
contenido real**:

1. **Fase C — catálogo de reglas** (#693/#697, duplicados): investigados en sesiones previas de
   este mismo worktree. El catálogo/UI para cargar reglas ya existe (obra de #121), pero
   `talento_compensation_rules` sigue en **0 filas** — nadie cargó una regla real ni contestó las 4
   preguntas de negocio pendientes (rol piloto, KPI/fórmula real, ventana de clawback, origen de
   datos de "recuperado cobranza"/"csat"). Ambos quedaron documentados como bloqueados esperando
   decisión de Irving (`docs/talento-comision-kpi-catalogo-item-693-verificacion.md`,
   `docs/talento-kpi-roles-no-tecnicos-item-697-verificacion.md`).
2. **Fase B — motor de preview READ-ONLY** (#698/#694, duplicados): #698 fue investigado y cerrado
   en una vuelta previa de este mismo worktree (`docs/talento-motor-kpi-preview-item-698-verificacion.md`).
   Conclusión de esa vuelta, confirmada con Thomas: sin filas reales en
   `talento_compensation_rules` no hay ningún `formula_config` real que leer, así que **el motor de
   preview nunca se construyó** — construirlo habría significado inventar la estructura/DSL de
   evaluación de KPI sin datos de Irving. El item quedó documentado y bloqueado; #694 (su duplicado)
   sigue en `requiere_irving` sin tocar.
3. **Fase A — motor activo + clawback real** (#700, este item): re-verificado en esta vuelta que
   `talento_compensation_rules` **sigue en 0 filas** (query directa) y que #697/#698 siguen sin
   cerrar con reglas/motor reales.

Es decir: la precondición explícita de #700 ("Fase B validada por Irving con datos reales") **no
solo no está validada — Fase B ni siquiera llegó a construirse**, porque a su vez dependía de una
Fase C que tampoco cerró.

## Por qué no se ejecuta el motor en esta vuelta

Construir el motor **activo** de pago + clawback ahora mismo requeriría, como mínimo, todo lo que
Fase B ya se negó a inventar (estructura de `formula_config`, fórmula real de KPI, fuente de datos
de "recuperado cobranza"/"csat") **más** la pieza específica de Fase A que el propio item señala
como indefinida: "requiere definir qué cuenta como 'cliente activado por este vendedor/agente' y
cómo se liga la comisión pagada a ese cliente para poder revertirla". Ninguna de esas definiciones
existe todavía en el repo ni en una respuesta de Irving. Escribir el motor implicaría inventar la
lógica de negocio que decide cuánto se paga y cuándo se revierte dinero real — exactamente la
frontera dura de dinero que el des-trabe (Opus) recomendó evitar hasta agotar las fases B y C.

No fue necesaria una consulta nueva a Thomas para esta conclusión: es la misma pregunta que ya
resolvió para el eslabón inmediato anterior (#698, "PROCEDE con documentar y bloquear, sin escribir
código de cálculo") aplicada un nivel más arriba en la misma cadena, con la misma precondición
objetivamente incumplida (verificada por query, no por criterio). No se cruza ninguna frontera dura
al elegir NO construir el motor de pago — al contrario, construirlo sin las dos fases previas
resueltas sí la habría cruzado.

## Cierre de esta vuelta

- Sin cambio de código de aplicación: ningún motor de pago/clawback escrito, `LiquidationService`
  y el ciclo de liquidación real intactos, como exige el propio item.
- Este documento deja registrada la cadena completa (C→B→A) y por qué los 3 eslabones siguen
  bloqueados en el mismo punto: falta que Irving defina las reglas reales de KPI/comisión (rol
  piloto, fórmula, ventana de clawback, fuentes de datos) en `/talento/compensacion`.
- El item permanece bloqueado. Cuando Irving cargue reglas reales, el orden correcto sigue siendo
  Fase C (cargar reglas) → Fase B (construir y validar el preview con esas reglas) → recién
  entonces Fase A (este item, con aprobación explícita adicional para mover dinero real).
