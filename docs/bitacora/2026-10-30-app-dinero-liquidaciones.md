## 2026-10-30 — App: Compensación y Liquidaciones como en la web

Pedido de David (probando la app 1.22 con la cuenta de prueba de Mostrador): «en compensación
debería poder ver el botón de calcular… debería ser lo mismo que en la web, ahí faltan cosas» y
«en liquidaciones, como mostrador, no veo lo que debe cobrar cada cual».

**Qué faltaba en la app frente a la web.**
- Compensación mostraba solo un resumen (neto, puntos, cuota, pago proyectado y conceptos). La web
  tiene «Mi dinero» con Cuenta, Desglose (de qué se compone el pago: sueldo base, faltas,
  sobreproducción, bonos, descuentos, préstamo y fondo), Fondo y Préstamos, y la pestaña
  «Compensación» con la regla de pago (sueldo base, cuota, valor por unidad).
- Liquidaciones mostraba solo las propias. En la web el Mostrador ve en «Liquidaciones» cuánto cobra
  cada quien por semana, con el botón «Comprobante»; administración ve todas, con Calcular, Ver y
  Cerrar.

**«Calcular» y el Mostrador.** En la web el Mostrador tampoco tiene «Calcular»: desde el 22-oct
(migración `2026_10_22_100000_mostrador_como_tecnico_en_talento`) ve cuánto cobra cada quien sin
desglose ni cálculo; calcular y cerrar es de quien tiene `talento.liquidation.manage`. La app sigue
la misma regla: el botón sale solo a quien el servidor autoriza. No se cambiaron permisos.

**Servidor** (merge `5baa71858`):
- `/talento/api/portal/compensacion` agrega `fondo`, `prestamos` (los mismos cálculos que
  «Mi dinero», ahora en `PortalTecnicoController::fondoDe()/prestamosDe()`) y `regla` (historial de
  reglas asignadas).
- `/talento/api/portal/liquidaciones/lista`: la misma consulta de la pantalla de la web
  (`TalentoLiquidacionController::data`), con `disponible`, `puede_calcular` y, para quien calcula,
  la lista de colaboradores activos. Sin `talento.liquidation.view` ni `.totales` → `disponible:false`.
- `/portal/liquidaciones/{id}` (Ver), `POST /portal/liquidaciones/calcular` y
  `POST /portal/liquidaciones/{id}/cerrar`: llaman a las mismas acciones de la web, con sus mismos
  permisos y reglas (no se recalcula nada aparte).
- Comprobante: `/portal/liquidaciones/comprobante-url` da un enlace firmado de 10 minutos a
  `/talento/api/comprobante-pago-firmado` (con quién lo entrega, que sale impreso); ahí se vuelven a
  revisar los permisos de esa persona. Bajo `/talento/api` abre también desde fuera de la oficina.
  (Primero se puso en `/talento/api/liquidaciones/comprobante-firmado`, pero esa dirección la
  atrapaba la ruta de la web `liquidaciones/{id}` y mandaba al inicio de sesión.)
- `/portal/liquidaciones` agrega `nombre`, `puede_comprobante` y `puede_calcular`.

**Probado** (vista previa de la rama y después en dev por https://dev.meganett.com.mx, con llaves
temporales de prueba_mostrador y prueba_admin, ya borradas):
- Mostrador: lista de 17 personas de la semana que toca pagar, 9 semanas para elegir; comprobante
  → PDF; con la firma alterada o sin firma → 403; persona sin sueldo → 422; Ver de otro → 403;
  Calcular y Cerrar → 403.
- Administración: lista de las 5 liquidaciones con Ver y Cerrar; detalle con 10 órdenes y 3
  movimientos; Calcular y Cerrar dentro de una transacción deshecha (200, quedó cerrada; semana
  anterior a julio → 422); al final 0 liquidaciones y 0 movimientos nuevos.
- La app no se pudo abrir en un teléfono: se simularon las dos pantallas con las respuestas reales
  del servidor (Mostrador, administración y desde Mi equipo), 20 de 20 revisiones bien.

**App v1.23** (`/home/meganet/TalentoEquipo`, rama `feat/dinero-liquidaciones-web` sobre
`feat/credencial-plantilla-url`, la de la v1.22; ninguna mezclada a `master`): `4fe4362`
(`CompensacionPanel` y `LiquidacionesPanel`) + `0de83fc` (release: versionCode 123, versionName
1.23, bundle). Compilada con `npm run build:apk`; `aapt`/`apksigner`: `com.meganet.talento` 1.23
(123), «Meganet», misma firma que la v1.22 (se instala encima sin borrar datos), 62.5 MB.
Publicada en https://dev.meganett.com.mx/downloads/meganetV123.apk; descarga verificada por el
dominio y por la IP de la oficina (MD5 `d97e4548c0cfbc962a98abdbd6904d49`). No registrada en
`talento_app_releases`: se instala desde el enlace.
