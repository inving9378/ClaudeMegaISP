## 2026-09-23 16:50 — Tema Torre en las 4 pantallas de VoIP + permisos reales de Asterisk destapados

### Tema visual

Se aplicó el tema "Torre de Control" (`.tc-wrap`/`.tc-dark`, ya usado en Vendedores/Talento) a las
4 pantallas del módulo VoIP: `VoipTroncales.vue`, `VoipExtensiones.vue`, `VoipGruposTimbrado.vue`,
`VoipIaBotManager.vue`. Las 4 usan Bootstrap plano (no Quasar), así que el tema ya cubre `.card`,
`.btn-*`, `table.table`, `.modal-content`, `.form-control`/`.form-select` sin tocarlos — el trabajo
real fue:
- Envolver la raíz en `tc-wrap` + `:class="{ 'tc-dark': darkMode }"` (import + `setup()` de
  `hook/appConfig.js`, mismo patrón que las 4 pantallas de Talento).
- Convertir todos los `.badge bg-*` a `.tc-status is-*` (los badges de Bootstrap se rompían en
  oscuro por una regla global `!important`).
- `<select>` → clase `tc-select` (chevron propio).
- `VoipIaBotManager.vue`: burbujas de chat con `bg-light` (blanco fijo, ilegible en oscuro) →
  clases propias `tc-turn-user`/`tc-turn-bot` con los tokens `--tc-*`.

**Hallazgo de Irving en vivo — botones de acción de fila invisibles:** los `btn-group` de
verificar/editar/eliminar (botones `btn-outline-*` reales, no el patrón `<a id-item>` del
datatable compartido) heredaban fondo sólido de la regla global `.btn-outline-danger` del tema
(pensada para botones normales) AL MISMO TIEMPO que la regla `td .fa-trash{color:var(--tc-bad)}`
(pensada para el datatable) — icono rojo sobre fondo rojo = invisible. Fix en
`_torre-theme.scss`: nueva regla `td .btn-group .btn { background:transparent; border-color:
transparent; color:var(--tc-muted); }` con hover sutil — reusable para cualquier pantalla futura
con el mismo patrón (botones Bootstrap reales agrupados dentro de una `<td>`).

**Hallazgo de Irving en vivo — "Provisionada Not provisioned" en la misma celda:** eran dos cosas
distintas apiladas (el flag `provisionado_at` de la BD vs. el chequeo en vivo contra Asterisk vía
AMI), y el chequeo en vivo solo traducía 4 de los 8 posibles status que devuelve
`AsteriskProvisioningService::verificarRegistro()` (`Registered/Registering/Rejected/n/a` sí,
`Stopped/Unknown/Error AMI/Not provisioned` no — salían en inglés crudo). Fix en
`VoipTroncales.vue`: métodos `verifEtiqueta()`/`verifClase()` que traducen los 8 casos.

### El hallazgo de fondo — permisos reales rotos desde el reinicio de Asterisk del 10-sep

Investigando el "Not provisioned", se destapó que **desde que Asterisk se reinició el 10-sep**,
`/etc/asterisk` volvió a los permisos de paquete (`asterisk:asterisk 0750`) — `www-data` (el
usuario real con el que corre la web) no tenía NI SIQUIERA lectura ahí. Efecto en cascada:
- El chequeo "Verificar" (`blockExists()`) SIEMPRE devolvía `false` — no importaba si el bloque
  de verdad existía o no, `file_exists()` fallaba por permisos y se disfrazaba de "no existe".
- El botón "Provisionar" (`file_put_contents()`) estaba roto igual — cualquier troncal nueva desde
  el 10-sep en dev NO se estaba escribiendo de verdad en Asterisk, aunque la pantalla no mostrara
  ningún error visible al usuario (el flujo normal de la UI no expone esto).

**3 pasos aplicados por Irving (root), verificados end-to-end por mí después de cada uno** (con un
script temporal en `public/`, autoborrado, que corre el código REAL de
`AsteriskProvisioningService::provisionar()` a través de nginx+php-fpm como `www-data` — no una
simulación por tinker, que corre como `meganet` y no refleja los permisos reales del proceso web):

1. `usermod -aG asterisk www-data` + `systemctl reload php8.2-fpm` → arregla LECTURA (grupo `asterisk`
   da `r-x`, no escritura).
2. `sudo php artisan voip:provisionar -n` (el instalador completo de Asterisk, `--descubrimiento`/
   `--desinstalar` no usados) → re-corrido porque no se había vuelto a ejecutar desde el reinicio del
   10-sep; recreó/confirmó `/etc/asterisk/megaisp.d/` con sus dependencias, sin recompilar/reinstalar
   Asterisk (ya estaba instalado, "no se reinstala encima"). "Provisión completa", 0 errores.
3. `chmod g+w /etc/asterisk/megaisp.d` → el paso que de verdad destapó el bug: el directorio seguía
   sin permiso de escritura de grupo tras el paso 2. Verificado ANTES (con el script temporal):
   `file_put_contents(...megaisp_registrations.conf): Permission denied`, ejecutado como `www-data`
   real. Verificado DESPUÉS: `{"ok":true,"provisionado_at":"2026-09-23 07:41:37"}`.

**Alcance del `chmod`:** solo esa subcarpeta (`megaisp.d`), no `/etc/asterisk` completo — el resto
de la config de Asterisk (`pjsip.conf`, `asterisk.conf`, etc.) sigue con los permisos de paquete
intactos.

### Verificado

- Los 4 componentes VoIP compilan limpio (`npm run dev`, 0 errores) con el tema aplicado.
- El botón "Provisionar" de troncales quedó operativo de nuevo en dev — confirmado por Irving en
  vivo y de forma independiente por mí con una llamada real al método, dos veces (antes y después
  del `chmod`).
- Pendiente (no bloqueante, fuera de esta sesión): repetir el mismo chequeo de permisos en
  producción si Asterisk se reinstala/reinicia ahí también — el patrón de causa raíz (reinstall →
  permisos de paquete por defecto → `www-data` sin acceso) es replicable en cualquier entorno.
