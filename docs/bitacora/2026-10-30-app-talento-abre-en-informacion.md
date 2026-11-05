## 2026-10-30 — App: la pestaña Talento abre directo en Información

Pedido de David (ya con la app 1.23 instalada): «como mismo en la web te lleva directo a la pestaña
info de talento, acá se necesita que vaya directo ahí». Antes, la pestaña Talento de la app mostraba
una lista de secciones y había que tocar «Información» para verla.

**Cambio** (`/home/meganet/TalentoEquipo`, rama `feat/talento-abre-en-informacion` sobre
`feat/dinero-liquidaciones-web`; ninguna mezclada a `master`): `ec7e93e`. Las secciones pasan a ser
pestañas arriba, como en la ficha de la web, y el contenido se ve debajo, empezando por Información
(con «Mi credencial»). Cada pestaña es la misma pantalla de antes, sin cambios. El selector «Viendo:»
del supervisor sigue arriba; si la pestaña elegida no aplica a la persona que se ve (p. ej.
«Penalizaciones de mi equipo» al ver a otro), vuelve a Información. Sin cambios en el servidor.

**Versión.** Desde hoy una corrección no sube la versión (pedido de David): se recompiló la misma
1.23 (versionCode 123, misma firma) y se reemplazó
https://dev.meganett.com.mx/downloads/meganetV123.apk (MD5 `397d5c87c69bea560190d4bd4016d1cb`,
verificado por el dominio y por la IP de la oficina; el teléfono la instala encima). Commit del bundle:
`eeb2df9`. La versión sube solo cuando se agrega algo nuevo.

**Probado** con la pantalla simulada (respuestas de prueba): Mostrador abre en Información con su
credencial, sin pestañas de campo ni de equipo, y al tocar Compensación cambia el contenido;
supervisor técnico abre en su Información con pestañas de campo y de equipo, y al elegir a alguien de
su equipo ve la Información de esa persona sin la credencial propia. 12 de 12 bien.

## 2026-10-30 — App: quien no tiene órdenes abre la app en Talento → Información

David instaló la 1.23 recompilada: «no entró directo a Información, siguió en Asistencia». El cambio
anterior solo tocaba la pestaña Talento; la app seguía arrancando en «Mi día», que para Mostrador y
vendedor es solo su asistencia de hoy.

**Cambio** (rama `feat/talento-abre-en-informacion`, `2797e15`):
- Quien no tiene órdenes (Mostrador, vendedor) abre la app en Talento → Información, como la web.
  El técnico sigue abriendo en «Mi día», donde están sus órdenes. Para saber con cuál abrir se usa la
  persona guardada en el teléfono (sirve sin red); un perfil guardado por una versión vieja, sin rol,
  abre en «Mi día» como antes y se corrige solo al abrir otra vez.
- «Mi día» se sigue cargando desde el arranque aunque no sea la primera pestaña: ahí se revisa si
  hay versión nueva de la app y se guardan las órdenes para usar sin red.
- Tocar «Talento» abajo regresa siempre a Información (antes se quedaba en la última pestaña vista,
  p. ej. Asistencia). Volver de firmar un documento o de un curso no cuenta: ahí se queda.
- La campanita abre la orden directo (va a la pantalla de la orden, no a una pestaña): sin cambios.

**Versión.** Misma 1.23 (versionCode 123, misma firma), bundle `f1015dc`. Reemplazado
https://dev.meganett.com.mx/downloads/meganetV123.apk (MD5 `ec2cb2ded24d033b855213eea6d2ab84`,
verificado por el dominio y por la IP de la oficina). El servidor no manda instrucción de caché para
el APK, así que el navegador del teléfono puede dar la copia anterior: para bajar la nueva se usa
`…/meganetV123.apk?v=3` (mismo archivo).

**Probado** con la pantalla simulada: arranque (Mostrador con y sin red abre en Talento; técnico y
perfil viejo abren en Mi día con sus pestañas; Mi día se carga desde el arranque) 6 de 6; pestaña
Talento (tocarla abajo regresa a Información y las pestañas de arriba vuelven al inicio; deja de
escuchar al cerrar) 6 de 6; las 12 revisiones anteriores siguen bien. No se probó en un teléfono.

## 2026-10-30 — App: todos abren en Talento → Información

David: «que en todos salga directo en Información». El técnico también abre la app en
Talento → Información; «Mi día» y «Órdenes» siguen en la barra de abajo. Rama
`feat/talento-abre-en-informacion`, `b70e1e5`. Como ya no depende del rol, se quitó la espera a leer
la persona guardada que había entrado en `2797e15`. Lo demás de la entrada anterior sigue igual:
«Mi día» se carga desde el arranque (aviso de versión nueva y órdenes para sin red) y tocar «Talento»
abajo regresa a Información.

Misma 1.23 (versionCode 123, misma firma), bundle `914121b`. Reemplazado
https://dev.meganett.com.mx/downloads/meganetV123.apk (MD5 `3d94107a0855e34ce2fe88c3b8c130cf`,
verificado por el dominio y por la IP de la oficina); bajarlo con `…/meganetV123.apk?v=4` para que el
teléfono no dé la copia guardada.

**Probado** con la pantalla simulada: arranque 7 de 7 (Mostrador con y sin red, técnico con sus
pestañas de Mi día y Órdenes, perfil viejo: todos abren en Talento; Mi día se carga desde el
arranque), pestaña Talento 6 de 6 y las 12 revisiones anteriores bien. No se probó en un teléfono.

## 2026-10-30 — App: todo lo de la 1.19 a la 1.23 queda en `master`

David confirmó en su teléfono que la 1.23 abre directo en Información y pidió juntar las ramas. En
`/home/meganet/TalentoEquipo`, `master` avanzó sin merge (historia lineal, como siempre en ese repo)
de `806f520` a `914121b`: 16 commits, de la v1.19 (`feat/talento-definiciones-21oct`) a la 1.23 de
hoy. Las cinco ramas (`feat/talento-definiciones-21oct`, `fix/dinero-semana-sin-calcular`,
`feat/credencial-plantilla-url`, `feat/dinero-liquidaciones-web`, `feat/talento-abre-en-informacion`)
quedaron completas dentro de `master`; no se borraron. `master` es exactamente lo que trae el APK
publicado (`meganetV123.apk`, MD5 `3d94107a0855e34ce2fe88c3b8c130cf`). El repo de la app no tiene
remoto: no se subió a ningún lado.

## 2026-10-30 — Préstamo de prueba a la cuenta de prueba de Mostrador (dev)

Pedido de David: un préstamo a la cuenta de prueba de Mostrador para verlo en el pago. Solo en dev,
sobre la cuenta de prueba (ficha 43), ninguna cuenta real. Préstamo `talento_loans` id 26: $1,000, abono
de $250 por semana, motivo «Préstamo de prueba…», registrado sin usuario y autorizado con la cuenta de
prueba de administración por `LoanService::authorize` (el mismo paso que el botón «Autorizar»).

Verificado por https://dev.meganett.com.mx con una llave temporal de la cuenta (ya borrada):
- Compensación → Desglose: sueldo base $1,500, «Préstamo y fondo» −$250, a pagar $1,250.
- Compensación → Préstamos: $1,000, saldo $1,000, 4 semanas restantes.
- Liquidaciones (semana 17→24 oct): «Prueba Mostrador QA» $1,250.
No se calculó ninguna semana: 0 liquidaciones y 0 abonos guardados; el saldo baja solo cuando alguien
con permiso calcule una semana. Se borra cuando se pida.

Nota: el préstamo no tiene semana de inicio, así que se resta en la proyección de cualquier semana sin
cerrar, incluso una anterior a la fecha del préstamo (aquí 17→24 oct); al calcular, se cobra en la
primera semana que se calcule.
