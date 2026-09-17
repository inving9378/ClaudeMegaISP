## 2026-09-18 — Auditoría: badges/contadores desincronizados de su filtro en Talento

**Pedido de Irving:** tras el fix de Asistencia (badge "N flagged" independiente
del rango de fecha, pero el checkbox sí se combinaba con él), revisar si otras
pantallas de Talento tienen el mismo defecto.

**Patrón buscado:** un badge/contador que se presenta como una señal "siempre
visible" (alerta, pendientes, flagged) pero que en realidad se calcula a partir
de datos que SÍ dependen de un filtro que el usuario puede cambiar — de forma
que cambiar el filtro para otra cosa apaga o falsea el contador sin que haya
menos casos reales.

**Revisadas las 29 pantallas de `resources/js/components/module/talento/`.**

### Bug real encontrado y corregido: `TalentoCredenciales.vue`

Pestaña "Alertas de vencimiento" — el badge rojo "N" se calculaba así:
```js
alertCount() {
  return (this.alertCredentials ?? []).filter(c => c.status === 'expired').length || null;
}
```
`alertCredentials` es la lista YA filtrada por el desplegable `alertFilter`
("Todos los estados" / "Por vencer" / "Vencidas" / "Sin registro") — el backend
solo devuelve el subconjunto pedido. Si el usuario cambiaba el filtro a "Por
vencer", `alertCredentials` dejaba de traer las vencidas, y `alertCount`
computaba 0 aunque sí hubiera credenciales vencidas reales — el badge se apagaba
por completo, dando una falsa sensación de "todo al día".

**Fix:** conteo separado (`expiredCount` + `loadExpiredCount()`), consultado
SIEMPRE con `status=expired` sin importar `alertFilter`, cargado en `mounted()`
y refrescado tras guardar/renovar una credencial (`saveCred()`). `alertCount`
computed pasa a leer de `expiredCount` en vez de filtrar la lista visible.

**Verificado por red** (Playwright, sin datos de prueba con credenciales
vencidas en esta BD para verificación visual, pero suficiente para confirmar el
mecanismo): al cargar la pantalla se disparan ambas consultas
(`/credentials/expiring` sin filtro + `/credentials/expiring?status=expired`);
al cambiar el desplegable a "Por vencer" solo se dispara la consulta de la
lista (`?status=expiring`) — la consulta del conteo NO se repite ni cambia, así
que el badge queda inmune al filtro.

### Revisadas y descartadas (mismo patrón buscado, sin defecto):

- **`TalentoPenalizaciones.vue`** — el badge "N" de apelaciones pendientes
  (`pendingAppeals`) se computa desde `this.appeals`, y el checkbox
  "pendingOnly" (default `true`) SÍ controla qué se carga ahí — pero como el
  badge cuenta sobre el mismo arreglo que alimenta la tabla, nunca se
  desincroniza: si el checkbox trae todas, el badge sigue contando bien; si
  trae solo pendientes, badge = tamaño de la lista. Sin fecha de por medio.
- **`TalentoDocumentosPendientes.vue`** — el badge por grupo (`grupo.docs.length`)
  se deriva del propio grupo ya renderizado, no de una fuente aparte. Sin filtro
  de fecha en la pantalla.
- **`TalentoRutas.vue`** — sí tiene un filtro de fecha por defecto (`today`,
  como tenía Asistencia), pero NO tiene ningún badge/contador global que lo
  contradiga — el estado vacío es honesto ("Sin rutas para esta fecha"), sin
  señal falsa de que debería haber algo más.
- **`TalentoProyectos.vue`**, **`TalentoFiniquito.vue`**, **`TalentoCompensacion.vue`**
  — sus usos de "hoy" son valores por defecto de campos de formulario (fecha de
  reporte, fecha de asignación, fecha de cálculo), no filtros de lista con un
  contador aparte.
- **Resto de pantallas** (25 restantes) — sin combinación de badge/contador
  independiente + lista filtrable que pudiera desincronizarse.

**Commit:** `TalentoCredenciales.vue`, pusheado a `main`.
