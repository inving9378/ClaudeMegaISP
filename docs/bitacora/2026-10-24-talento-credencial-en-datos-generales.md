## 2026-10-24 — Talento: la credencial va dentro de Información, en la tarjeta de Datos generales

**Pedido:** "las credenciales deben estar dentro de informacion en el card de Datos generales no en
una pestaña aparte".

**Qué cambia en pantalla**
- La ficha de Talento ya no tiene la pestaña **Credenciales**.
- En **Información**, la misma tarjeta de *Datos generales / Roles del sistema* trae abajo la sección
  **Credencial**: el carnet (frente y reverso) con *Descargar credencial*, *Editar foto y datos* y
  *Cambiar diseño* (cada botón con el mismo permiso que antes).
- Quien gestiona credenciales (administración, supervisores) ve ahí mismo, debajo del carnet, las
  credenciales de esa persona (licencia, vencimiento, *Documento*, *Editar*, *Registrar credencial*) y
  sus fondos de ahorro. Las listas de **todos** (alertas de vencimiento, fondos pendientes, selector
  de colaborador) ya no salen dentro de una ficha: siguen en **Talento › Credenciales**.
- Si alguien tenía guardada la pestaña Credenciales como la última abierta, la ficha abre en
  Información.

**Archivos**
- `TalentoColaboradorFicha.vue`: quita la pestaña y su panel; pasa `<talento-credenciales compacto>`
  dentro de `<talento-ficha-informacion>`.
- `TalentoFichaInformacion.vue`: slot al pie de la tarjeta (separado con una línea).
- `TalentoCredenciales.vue`: prop `compacto` (sin pestañas ni selector, no carga las listas de todos).
- `TalentoUserManualSeeder.php`: «Tu ficha de Talento» menciona la credencial en Información
  (en prod se aplica con el mismo comando del seeder de `2026-10-23-talento-manual-jarvis-sin-portal.md`).

**Pruebas** (servidor de la rama, navegador): 16/16 — la persona en su ficha (carnet con RFC, botón
editar, sin listas de gestión, abre en Información aunque tuviera guardada la pestaña vieja);
administración en la ficha de la persona (carnet + licencia de prueba con vencimiento, *Editar* abre
con las fechas llenas, sin alertas ni selector); Talento › Credenciales conserva sus tres pestañas.
