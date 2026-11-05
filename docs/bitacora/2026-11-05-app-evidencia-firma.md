## 2026-11-05 — App: la firma del cliente no se podía guardar

Reporte: «No deja guardar las evidencias la app o solo que solo sea en producción. Pero ahora la veo
en dev y las tareas que veo son de dev». Rama `fix/talento-firma-evidencia-app`.

**Causa comprobada.** El pad de firma de la app manda un PNG y `otEvidencia` solo aceptaba JPG desde
que se creó la API (4-jun-2026); el pad llegó el 8-jun. El técnico veía «foto debe ser un archivo con
formato: jpg, jpeg». Es igual en dev y en producción: es el mismo código. La firma es obligatoria en
Instalación nueva y Reubicación, así que esas órdenes no se podían cerrar desde la app.

**Arreglo** (solo servidor, no pide APK nuevo)
- `TalentoMobileApiController::otEvidencia`: acepta PNG solo si el tipo de evidencia es de firma. Las
  fotos siguen siendo solo JPG.
- `OrdenTrabajoUnifiedService::subirEvidencia`: guarda el archivo con su extensión real.

**Verificado.** `EvidenciaFirmaAppTest` (3 pruebas): antes del arreglo fallaba la de la firma con el
mismo mensaje. Contra la base de dev, en una transacción que se deshizo: foto JPG y lectura dBm responden
201. Faltó probarlo en un teléfono.

**Las fotos.** En el servidor de dev se guardan bien, así que si también fallan en el teléfono la causa
es otra y hace falta el texto exacto del error: los accesos de nginx no se pudieron leer con esta cuenta
y en la bitácora de Laravel no hay errores de esta ruta (un 422 no se registra ahí).

**Por qué veía dev.** La app se queda en el servidor que se escribió la primera vez, y dev y producción
responden con el mismo nombre («Meganet Telecomunicaciones»), así que en pantalla no se distinguen. Se
cambia en Mi día › ⋮ › Cambiar servidor.

**Visto y no tocado.** Al guardar una lectura de dBm la app muestra «Señal: undefined» porque lee
`categoria` y `mensaje` y el servidor manda `calidad`; la evidencia sí se guarda. `calidad` además da
«deficiente» para -20 dBm, que en la tabla de umbrales es Excelente, por eso no basta con cambiar el campo.

Quién: sesión de David (Claude Code).
