## 2026-10-08 19:55 — Monitoreo Oficial, Fase 8: validación final de todo el módulo junto

### Contexto

Última fase de la épica #9991236 (paraguas). A diferencia de las Fases 1-7 (cada una
construyó una pieza nueva), esta fase no agrega código — prueba las 8 piezas trabajando
**juntas**, de punta a punta, con datos reales desechables, y cierra la épica completa.

### Qué se verificó

**1. Control de permisos, en las 5 rutas protegidas del módulo.** Con un usuario sin
ninguno de los 5 permisos de Monitoreo Oficial (pero con un rol de planta real —
`Vendedor` — para que el login mismo funcione): dashboard, configuración, crear
expediente, crear fuente y ver un expediente devolvieron **403** los cinco. (La prueba
tuvo un falso-negativo inicial al usar un usuario con rol `client`/sin rol: ese login
pasa por el guard `LoginController::STAFF_ROLES`, ajeno a este módulo, que rechaza
cualquier cuenta sin un rol de planta — no es un hallazgo de Monitoreo Oficial, es
comportamiento ya existente del login. Corregido usando un rol de planta real sin el
permiso, que es la prueba correcta.)

**2. El flujo real completo, de punta a punta, con IA real (asignación temporal
`openai-default`, retirada al cerrar):**
- Fuente + expediente de prueba, con avisos configurados (correo y WhatsApp).
- Documento 1 (IMPI, "EN TRÁMITE") → análisis de IA correcto, sin marcar cambio (no
  hay documento anterior con qué comparar).
- Documento 2 (mismo expediente, "RESOLUCIÓN FAVORABLE") → la IA comparó contra el
  documento 1 y detectó correctamente el cambio real de estatus, con un resumen
  preciso en español.
- El dashboard reflejó el cambio al instante: 1 expediente, 1 activo, 1 con cambio
  reciente — insignia naranja en la fila correcta.
- La campana interna se disparó con el contenido exacto esperado (confirmado en la
  tabla `notifications`): "Monitoreo Oficial: cambio detectado en «EXPEDIENTE PRUEBA
  FASE 8...»" + el resumen real de la IA. El fix de la Fase 6 (clase anónima no
  serializable) sigue sosteniéndose bajo uso real.

**3. El comando programado** (`monitoring:revisar-expedientes`, registrado en
`command_configs` desde la Fase 4): `--dry-run` contó correctamente 1 expediente
activo; sin `--dry-run` lo encoló, el worker lo procesó y `last_checked_at`/
`next_check_at` quedaron sellados (+7 días).

**4. Preview de desinstalación** (`module:lifecycle preview-uninstall`, no destructivo):
reportó correctamente 5 permisos y 2 roles afectados (`super-administrator` +
`DESARROLLADOR`), 0 usuarios — consistente con lo verificado en la Fase 1.

### Hallazgo real de esta fase (no es un bug — es una nota de cuidado para quien pruebe)

A diferencia de la Fase 6 (donde deliberadamente NO se disparó un envío real de
WhatsApp, solo se verificó en código que el número se formatea bien), esta prueba de
punta a punta sí dejó el campo `notify_whatsapp` configurado con un número de prueba
y el flujo completo corrió tal cual lo haría en producción: **el aviso de WhatsApp
intentó de verdad salir hacia la API de Evolution** (quedó en el log como "Aviso por
WhatsApp falló: Evolution API error 500" — no se entregó nada porque Evolution
respondió error, no porque se haya frenado a propósito). El correo no tuvo este
riesgo porque se usó una dirección deliberadamente no entregable (mismo patrón de la
Fase 6). **Nota para quien vuelva a probar este módulo con datos reales:** si el
expediente de prueba lleva un número de WhatsApp real y válido, el sistema SÍ le va a
mandar un mensaje de verdad en cuanto la IA detecte un cambio — es el comportamiento
correcto de la Fase 6, no un defecto, pero hay que usar un número de prueba, nunca uno
real, al repetir esta prueba.

### Limpieza

Expediente, documento, fuente, 2 usuarios de prueba, la notificación de campana, la
asignación temporal de IA y los archivos físicos de los PDFs subidos (confirmado:
el hook de borrado de archivos de la Fase 3 sigue limpiando correctamente) — todo
borrado al cerrar, confirmado por conteo en cero.

### Estado de la épica completa (#9991236)

Las 8 fases quedaron construidas, mergeadas a `main` y verificadas con datos reales:
F1 estructura, F2 datos (expedientes/fuentes CRUD), F3 fuentes+PDF manual, F4
scheduler/colas, F5 análisis con IA, F6 notificaciones (correo/WhatsApp/campana), F7
pulido de interfaz, F8 esta validación final. El módulo queda **instalado y activo en
esta instancia de desarrollo, sin datos reales cargados** — la elección de proveedor
de IA permanente (hoy "sin configurar", a propósito) y la carga de expedientes reales
son decisiones de Irving, no de esta sesión.

### Estado

Mergeado directo a `main` (mismo patrón manual del resto del día). Esta es la última
fase — no queda trabajo pendiente de la épica salvo que Irving decida agregar algo
nuevo.
