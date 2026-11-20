# 2026-11-17 — VoIP: teléfonos físicos (1000–1999) y teléfonos web (2000–2999) en segmentos separados

## Pedido
En prod, Irving tiene el teléfono físico en la 1001 y su teléfono web en la 1011. De la 1011 a la 1001 entra bien; de
la 1001 a la 1011 no sale, y tampoco a las demás. Las extensiones 1002, 1003, 1004… son teléfonos físicos (mostrador)
y estaban «ligadas» a la web. Pidió segmentos: uno de teléfonos físicos y otro de teléfonos web, con «una solución
limpia». Eligió «Web por persona 2000–2999».

## Causa de que la 1001 no llegue a la 1011
- Cada extensión física tenía una gemela `webNNNN` (otra cuenta SIP) y el navegador se registra como `web1011`.
- En test, `extensions.conf` se editó a mano el 24-sep para que marcar `1011` timbrara `PJSIP/1011&PJSIP/web1011`
  (`2026-09-24-megavoz-dial-simultaneo-escritorio-web.md`). Ese cambio nunca pasó a la plantilla
  `resources/asterisk/plantillas/extensions.conf.tpl`, que es la que usó el instalador en prod: allá `1011` timbra solo
  `PJSIP/1011`, donde no hay ningún aparato. Desde el navegador sí entra a la 1001 porque el aparato está en `1001`.
- Además la gemela salía con `callerid` = número del aparato (1011): devolver la llamada timbraba al aparato.
- Las gemelas son cuentas distintas (otra contraseña): no «sacan» al teléfono físico. Lo de que no les entre a 1002–1004
  no está confirmado con el log de prod; si los aparatos están conectados y aun así no timbra, es otra cosa.

## Lo que se hizo (main da134646a)
- `Services/SegmentosTelefono.php`: segmentos, número espejo (1011 → 2011), primer libre por área, datos fijos del
  teléfono web (WSS, ulaw,alaw,opus).
- `Seeders/PlanNumeracionSeeder.php`: seis rangos web (`oficinas_web` 2000–2099, `atencion_web` 2100–2199,
  `tecnicos_web`, `ventas_web`, `direccion_web`, `reserva_web` 2500–2999) con los perfiles de su área.
- `Services/SeparadorTelefonosWeb.php`, corrido por `SincronizadorAsterisk` antes que todo (botón «Sincronizar con
  Asterisk» y paso 60 de «Instalar dependencias», que ya respalda la base antes): gemela con dueño → número propio con
  la misma terminación (o el primero libre de su área), `numero_anterior` = el viejo, `callerid` al nuevo; sin dueño →
  se quita; dos de la misma persona → se queda la activa más antigua (la que hoy le da `MiTelefonoController`) y le pasa
  los grupos. Un `webNNNN` que solo existe en Asterisk se quita (el navegador saca las credenciales de MegaISP).
- `ReclamadorExtensionAutomatico`: al dar de alta en Talento a Mostrador/Ventas/Técnicos crea su teléfono web en el
  rango web de su área; ya no toma un número físico ni crea gemelas.
- `ExtensionController`: sin gemelas al crear/editar/borrar; valida cada segmento; tipo «Teléfono web» (persona
  obligatoria, uno por persona, contraseña automática); al cambiar el número quita el endpoint viejo de Asterisk.
- Plantilla `extensions.conf.tpl` (y la copia de referencia): sin la regla `_web[1-9]XXX`; `_[1-9]XXX` →
  `Dial(PJSIP/${EXTEN},30)` sirve a los dos segmentos.
- Historial (`HistorialLlamadasService`): una llamada vieja de `web1003` se atribuye por `numero_anterior`.
- Pantalla de Extensiones: tipo «Teléfono web», ayuda de segmentos, insignia en la tabla. Teléfono del navegador: ya
  no acepta el prefijo `web`.
- Quitado `voip:generar-gemelas-webrtc`. Migración `2026_11_17_150000_add_numero_anterior_to_voip_extensiones`.

## Verificado en test (dev)
- Pruebas: `tests/Feature/VoIP/SegmentosTelefonoTest.php` (3), `PlanNumeracionTest` (16), `tests/Unit/VoIP` (43),
  `tests/js/numeroMarcado.test.mjs` (5): todo pasa.
- Respaldo antes de aplicar: `voip_extensiones`, `voip_grupo_extension`, `voip_rangos_numeracion` y `ps_*`/`queue_members`
  en el scratchpad de la sesión f643e010 (`v196/`).
- «Sincronizar con Asterisk» aplicado por la pantalla (como www-data): web1002→2002, web1003→2003, web1004→2004,
  web1011→2011; 27 gemelas sin dueño quitadas; `web1000` y `web9001` (solo en Asterisk) quitadas; la cola «Atención a
  Clientes» pasó de `PJSIP/web1003` a `PJSIP/2003`; 0 fallas. Una segunda revisión ya no tiene nada que separar.
- `extensions.conf` de test puesto igual a la plantilla con `provisioning/actualizar-extensions.sh`:
  `dialplan show 2011@from-internal` → `Dial(PJSIP/${EXTEN},30)`; `web1003@from-internal` ya no existe.
- De punta a punta: teléfono web 2090 dado de alta por la API de la pantalla para la cuenta de prueba (las validaciones
  rechazan físico en 2005, web en 1005, web sin persona y un segundo web de la misma persona); el navegador (Playwright)
  recibió 2090, se registró, y `Local/2090@from-internal` lo hizo timbrar («Llamada entrante…»). Luego se borró por la
  API y quedó fuera de MegaISP y de Asterisk.
- Historial: una llamada vieja de `web1003` sale como «<nombre de la persona> (2003)».
- Pantalla (Playwright, cuenta de prueba): insignia «Teléfono web» en 2002/2003/2004/2011 con «Antes web1003» al pasar
  el mouse; con el tipo «Teléfono web» se ocultan contraseña, códecs y transporte y la ayuda dice 2000–2999; guardar
  1050 sin persona marca los dos errores. Los errores salían como lista (`["…"]`), cosa que ya pasaba antes con
  cualquier error del formulario: ahora se muestra el primero como texto (main 438f15559).

## Versión
V1.96-17.11.2026 publicada a pedido de Irving («ok» tras preguntarle si compilar y publicar): commit 231b0d13a sobre
V1.95 (21 archivos, idénticos a main 438f15559), fila `releases` #172, `deployment_logs` #121, `-s ours` a main.
Compilación completa de la copia limpia: «Compiled successfully in 10.87m». La primera compilación la paró el sistema
por falta de memoria; se volvió a correr con 11 GB libres. Scripts en el scratchpad de f643e010 `v196/`.

## En prod
Con la versión: «Instalar dependencias» de VoIP hace la separación (y reescribe `extensions.conf` si es el que dejó el
instalador), y cada quien recarga la página para que su teléfono web tome el número nuevo.
