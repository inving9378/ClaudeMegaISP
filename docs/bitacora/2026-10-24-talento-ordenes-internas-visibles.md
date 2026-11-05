## 2026-10-24 — Las órdenes de tipos de trabajo «interno» no salían en Talento

**Reporte:** "acabo de crear una orden para prueba mostrador y cuando entro en Talento › Mi día no veo
nada, dice sin órdenes".

**Causa:** la orden (task 1695, «Reubicación», hoy 4:00 p.m.) se creó bien, pero en dev el tipo de
trabajo «Reubicación» está como trabajo interno, y «Asignar trabajo» guarda la orden con esa categoría
(`tasks.tipo = 'interna'`). Todo Talento buscaba las órdenes con `tipo = 'campo'`, así que una orden de
un tipo interno no salía en ningún lado de Talento: ni en Mi día, ni en Órdenes de trabajo, ni su
detalle, evidencia, firma o validación (solo en Agenda).

**Cambio:** una orden de Talento se reconoce por tener tipo de trabajo de Talento
(`talento_type_id`), sea de campo o interna. Se quitó el filtro `tipo = 'campo'` en
`OrdenTrabajoUnifiedService` (Mi día, historial, detalle propio, lista y detalle de administración,
editar, cambiar estado, validar, actividades) y en `FieldFlowEntity::resolve` (evidencia y firmas).
Las tareas normales de Agenda (sin tipo de Talento) siguen fuera.

**No se tocó el pago:** `LiquidationService::countBillableUnits()` sigue contando solo órdenes de
campo. Hoy no cambia nada (el único tipo interno, Reubicación, vale 0 puntos); si algún día un tipo
interno lleva puntos, decidir si cuentan para el pago.

**Prueba** (código de la rama contra la BD de dev, solo lectura): como `prueba_mostrador`,
`/talento/portal/ots/hoy` → «#1695 Reubicación» y `/talento/portal/ot/task/1695` → 200 con sus 4
evidencias requeridas (en main: lista vacía y 404). En administración, la lista y el detalle la
incluyen. Una tarea normal de Agenda sigue sin reconocerse como orden.
