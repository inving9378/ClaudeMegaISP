## 2026-10-23 — Talento: se retira el Portal de Colaborador aparte

Decisión del usuario: todo debe estar en un solo lugar, la ficha de Talento. Con «Mi día»,
«Mi dinero», «Mis prospectos» y «Mi equipo» ya como pestañas de la propia ficha (ver las bitácoras
`2026-10-23-talento-ficha-*`), el Portal aparte se retira:

- Se quita la pestaña «Mi trabajo (Portal)» (el Portal metido en un recuadro dentro de la ficha).
- `/talento/portal` ya no muestra el Portal: lleva a «Mi día» de la propia ficha (o a Talento si la
  persona no tiene ficha). Así no se rompen los accesos guardados.
- Quien instaló el Portal en el celular: su app pide `/talento/portal/sw.js` al revisar
  actualizaciones; ahora recibe un service worker que borra el caché del Portal y se da de baja.
- El botón «Mi portal» del menú de usuario pasa a ser «Mi día» y abre la propia ficha en esa
  pestaña; solo sale a quien tiene ficha.
- En Asistencia, «Registrar entrada o salida» cambia a la pestaña «Mi día» de la misma ficha.
- La ficha acepta `?tab=` para abrir una pestaña, y si la pestaña recordada ya no existe (por
  ejemplo «Mi trabajo (Portal)») abre Información en vez de quedar en blanco.
- Se borran el shell del Portal (`views/portal/shell.blade.php`, `public/talento-portal/*`), el
  manifest y el guardado del tema del Portal. Los endpoints `/talento/portal/*` siguen: son la
  fuente de las pestañas nuevas.

Lo que el Portal tenía y ya estaba en la ficha no se duplicó: «Mi material» = Custodia, «Mis
documentos» = Paquetes de documentos, «Mi progreso» del técnico y del Mostrador = Información,
«Perfil» = menú de usuario. El «Mi progreso» del vendedor no estaba en la ficha: ahora sale en
Información de su propia ficha (la misma pantalla de «Mis ventas»).

Pendiente: el manual de usuario (`TalentoUserManualSeeder`, capítulos «Portal de Colaborador») y la
guía de JARVIS de Talento todavía describen el Portal.
