# Plan maestro de unificación (épica UNI)

Plan para unificar Talento, Tareas, Scheduling, Vendedores, órdenes y accesos en una sola experiencia por colaborador («Mi Espacio»), sin reconstruir MegaISP y sin perder datos. **Etapa solo documental**: nada de lo descrito está implementado. Contrato: adjunto `plan-maestro-unificacion.md` de la épica (#679).

## Documentos

| Doc | Archivo | Una línea |
|---|---|---|
| 01 | [`01-estado-actual.md`](01-estado-actual.md) | Estado actual consolidado: qué funciona, qué falta, qué está duplicado o roto |
| 01a | [`01a-talento.md`](01a-talento.md) | Auditoría de Talento: identidad, ficha propia, campo, incentivos, app |
| 01b | [`01b-agenda.md`](01b-agenda.md) | Auditoría de Tareas y Scheduling: motor de agenda y calendario |
| 01c | [`01c-vendedores.md`](01c-vendedores.md) | Auditoría de Vendedores: ventas, atribución, metas y comisiones |
| 01d | [`01d-ordenes.md`](01d-ordenes.md) | Auditoría de tickets, órdenes, instalaciones y garantías |
| 01e | [`01e-base-transversal.md`](01e-base-transversal.md) | Identidad, accesos, notificaciones, auditoría y API |
| 02 | [`02-arquitectura.md`](02-arquitectura.md) | Arquitectura propuesta: servicios compartidos y dueño de cada dato |
| 03 | [`03-mapa-funcionalidades.md`](03-mapa-funcionalidades.md) | Qué se conserva, adapta, fusiona o retira |
| 04 | [`04-experiencia-usuario.md`](04-experiencia-usuario.md) | Pantallas por perfil, «Mi Espacio», orden rápida y uso en celular |
| 05 | [`05-plan-migracion.md`](05-plan-migracion.md) | Integración sin pérdida de datos: fases A–G, conciliación y reversión |
| 06 | [`06-roadmap.md`](06-roadmap.md) | Roadmap por fases, paralelismo, items propuestos y preguntas que bloquean el arranque |

## Orden de lectura recomendado

1. **`01-estado-actual.md`**: el diagnóstico en 10 minutos (las 01a–01e son la evidencia; se consultan solo para un detalle).
2. **`02-arquitectura.md`**: cómo se conecta todo y quién es dueño de cada dato.
3. **`03-mapa-funcionalidades.md`**: qué sobrevive y qué se retira después.
4. **`04-experiencia-usuario.md`**: lo que verá cada perfil.
5. **`05-plan-migracion.md`**: cómo se mueve sin perder nada.
6. **`06-roadmap.md`**: qué se hace primero y qué decisiones se necesitan de Irving (sección 7).

Para decidir rápido: leer el resumen ejecutivo de `01` y la sección «Preguntas para Irving que bloquean el arranque» de `06`.

## Reglas de la épica

- El repositorio es público: estos documentos solo llevan conteos agregados y nombres técnicos; nada de personas, contactos, montos por persona ni credenciales.
- Las correcciones #691–#694 van por su cuenta; #695 (UNI-07) actualiza estos documentos con su resultado.
- Los items de implementación se dan de alta cuando Irving apruebe el plan.
