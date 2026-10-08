## 2026-10-08 17:50 — Monitoreo Oficial, Fase 2: CRUD de expedientes y fuentes

### Contexto

Continuación directa de la Fase 1 (item #9991237, mergeada hoy). La épica #9991236 enumera 8
fases; "F2 datos" era la siguiente. No existía todavía un sub-item formal en la Hoja de Ruta para
esta fase — se construyó directo, documentando aquí.

### Qué hacía falta

La Fase 1 dejó las tablas `monitoring_sources`/`monitoring_cases` creadas, los 5 permisos
(`monitoring.manage_cases`, `monitoring.manage_sources`, etc.) ya definidos en `module.json`, pero
**ningún formulario** los usaba — el dashboard solo listaba (de solo lectura) el expediente de
ejemplo si existía. Sin forma de dar de alta un expediente o fuente real, el módulo no era
utilizable todavía.

### Qué se construyó

- CRUD completo de **expedientes** (`monitoring_cases`): crear, editar, eliminar — con selector de
  fuente asociada, validación de expediente único por autoridad, y un candado explícito: el
  expediente de ejemplo de MEGANET (`is_demo=true`) **no se puede borrar desde la pantalla** (solo
  se retira al desinstalar el módulo o a mano, con una decisión explícita).
- CRUD completo de **fuentes oficiales** (`monitoring_sources`): crear, editar, eliminar. Al
  borrar una fuente que un expediente ya usa, el expediente **no se bloquea ni se borra** — queda
  sin fuente asociada (la FK ya tenía `nullOnDelete()` desde la Fase 1, se confirmó que de verdad
  se comporta así).
- Ambas pantallas (`dashboard`/`configuracion`) actualizadas con los botones de alta y las
  acciones editar/eliminar por fila, gateadas por los permisos ya existentes
  (`monitoring.manage_cases`/`monitoring.manage_sources`) — nadie sin el permiso ve los botones.
- Corregido en el camino: dos bloques `@can`/`@endcan` que había escrito al construir esto
  violaban la convención dura del repo (Blade debe usar `@if(auth()->user()->can('x'))`, nunca
  `@can()`) — se corrigieron antes de probar en navegador.

### Verificación

Ciclo completo en navegador con cuenta de prueba (creada y borrada): crear una fuente → aparece en
Configuración → crear un expediente usando esa fuente → aparece en el Dashboard con la fuente
correctamente mostrada → editarlo (cambiar su estado a "Pausado") → el cambio se refleja → eliminar
el expediente → desaparece → eliminar la fuente → desaparece. Cero errores de consola. Datos de
prueba confirmados en cero en la base de datos al terminar.

### Pendiente (fases siguientes de la épica, fuera de esta Fase 2)

F3 (la propia fuente ya avisa: "la descarga automática por fuente se habilita en la Fase 3"), F4
scheduler/colas, F5 IA, F6 notificaciones, F7 UI, F8 validación.

### Estado

Mergeado directo a `main` (mismo patrón manual del resto del día) — commit a continuación de este
archivo.
