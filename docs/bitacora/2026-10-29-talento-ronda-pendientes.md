## 2026-10-29 — Ronda de lo que faltaba probar de lo que pidió Irving

En dev, después de mezclar «inactivos fuera de las listas». Cuentas de prueba (administrador,
Mostrador, técnico); lo que se guardó se regresó al terminar.

| Prueba | Resultado |
|---|---|
| Ticket nuevo, «Asignado a» (administrador y Mostrador) | 6 puestos, 24 personas, ningún inactivo |
| Ticket viejo asignado a una persona inactiva | sale el aviso «ya no está activo»; al guardar se manda la persona elegida (envío interceptado, no se guardó) |
| Ver ticket → «Generar tarea de esta solicitud» | 24 personas sin inactivos; con proyecto, 2 |
| Equipo con 6 miembros inactivos (editar) | los 7 miembros siguen elegidos, los inactivos con «(inactivo)»; no se ofrece ningún otro inactivo |
| Técnico del prospecto | 6 técnicos activos |
| «Pago a técnico», técnicos de caja (Talento y Vendedores), personas de proyecto | 6, 6, 6 y 2, ningún inactivo |
| Responsable de almacén, líder de proyecto, destino de inventario | 24, 5 y 24 activos; uno inactivo guardado sale «(inactivo)» |
| Origen de inventario | 40 (con inactivos, a propósito) |
| Técnico: «Registrar entrada» en «Mi día» | entrada en «BODEGA SANTA ANA», sin alerta; luego «Registrar salida» y «Salida registrada» |
| Técnico: «Editar foto y datos» | teléfono y foto guardados; al recargar siguen; la credencial PDF se genera |

Hallazgos:
- **La entrada pide ubicación y el navegador solo la da con candado (https).** Por
  https://dev.meganett.com.mx funciona; por la IP (http://192.168.105.11) el botón responde
  «Necesitamos tu ubicación…». Producción va por https, así que ahí sí funciona.
- **Mostrador ya no entra a Talento.** Entre la tarde del 28-oct (a las 14:53 la cuenta de prueba
  entraba a su ficha) y las 2:00 del 29-oct (respaldo diario), el rol Mostrador perdió sus 17 permisos
  de Talento (talento.view, liquidation.totales, work_orders.view/manage, attendance.view, etc.). No
  hay commit ni migración que lo haga y no quedó registro de quién: fue un cambio de datos. Diana
  tiene los mismos permisos que la cuenta de prueba, así que no puede abrir su ficha ni la lista de
  pagos ni imprimir comprobantes. No se tocó: los permisos los está acomodando Irving.

Se regresó: la entrada y salida de prueba (talento_attendances), el teléfono y la foto del técnico de
prueba (y el archivo subido), su registro en activity_log y las sesiones de prueba.

## 2026-10-29 — Permisos de Mostrador regresados y ubicación de la asistencia en la app

**Permisos (pedido de David, sesión del 29-oct):** se regresaron al rol Mostrador los 17 permisos de
Talento que tenía en el respaldo del 28-oct 02:00 (talento.view, .academy.view, .attendance.view,
.comisiones.view, .custody.view, .dashboard.view, .devices.view, .embajadores.view,
.health_bonus.view, .liquidation.totales, .location.view, .media.view, .signatures.view,
.warranty.view, .work_orders.manage, .work_orders.view, .work_sites.view). Solo se agregaron
(`givePermissionTo`), no se quitó nada: el rol pasó de 208 a 225 permisos (5 usuarios). Caché de
permisos limpiada. Verificado con la cuenta de prueba de Mostrador por https: entra a su ficha en
«Mi día», registra entrada («BODEGA SANTA ANA», sin alerta) y salida, guarda teléfono y foto de la
credencial, abre la lista de pagos e imprime un comprobante (PDF). Todo lo guardado se regresó.

**Ubicación de la asistencia en la app** (`/home/meganet/TalentoEquipo`, `AsistenciaPanel`):
- Pide el permiso de ubicación del teléfono (ACCESS_FINE_LOCATION, declarado en el manifiesto) y usa
  el GPS nativo (`react-native-geolocation-service`); no depende del candado (https) como el
  navegador. Mostrador y vendedor la tienen en «Mi día» desde la v1.19.
- Probado el lado del servidor con una llave temporal de la cuenta de prueba de Mostrador:
  entrada GPS en la bodega 201 sin alerta, «hoy» la devuelve, salida 200.
- Si el GPS falla, la app deja escribir una dirección (`/talento/api/geocodificar`, Google
  Geocoding). En dev no funciona: la llave de Google de `.env` no tiene activada la Geocoding API
  («REQUEST_DENIED: This API is not activated on your API project»). En producción falta confirmarlo.
- Una dirección escrita a mano dentro de la geocerca entra **sin alerta**, igual que GPS; fuera sí
  se marca. La forma (`check_in_location_source`) se guarda pero ninguna pantalla la muestra.
- La app no usa la precisión del GPS para marcar alerta (la web sí).

Se borraron las 3 entradas de prueba y la llave temporal.
