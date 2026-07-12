# Candidatos a preferencia — pendientes de revisión batch (item #351)

> Loop de aprendizaje del [perfil de decisiones de Irving](perfil-decisiones-irving.md) (Opción 2,
> semi-automática con revisión batch — recomendada en el brief C del item #351 y aprobada por Irving
> al soltar el item al pool). Cada vez que Irving decide un item de su bandeja (`POST
> /api/roadmap/circuito/decidir`), el sistema APPEND-EA aquí un candidato crudo — **NO** lo inlinea
> solo en `perfil-decisiones-irving.md`. Irving revisa esta lista en lote (p.ej. semanal) y decide
> a mano qué mover al perfil vivo.

## Reglas (no negociables)
1. **Solo Irving edita `perfil-decisiones-irving.md`.** Este archivo es un buzón de candidatos, nunca
   se auto-inlinea al perfil.
2. **Frontera dura (dinero/seguridad/prod/negocio) NUNCA se infiere automáticamente** — ver siempre
   ese archivo directamente para esos casos; aquí solo se registra el crudo trazable.
3. **Umbral sugerido:** antes de promover un candidato a preferencia real, buscar ≥3 decisiones
   consistentes sobre el mismo patrón (mismo módulo/categoría/tipo de riesgo). 1-2 decisiones sueltas
   son ruido, no regla.
4. **Trazable:** cada candidato cita el item `#N` de origen (para poder ir a leer el contexto completo).
5. **Versionado en git** (este archivo vive en el repo) → cualquier promoción o poda es reversible.

## Candidatos capturados

<!-- CIRCUITO:APPEND-ANCHOR — no editar esta línea, el capturador automático agrega debajo -->
