## 2026-10-14 — Permisos de IA: al dárselos a otro usuario el robot JARVIS no le aparecía

**Reporte:** "en los permisos de la IA, cuando se los doy a otro usuario no le salen".

**Causa:** hay dos juegos de permisos distintos y la pantalla de permisos solo enseñaba uno.
- La pestaña **IA → "Asistente IA"** contiene los `ia_*` (`ia_view_chat`, `ia_add_chat`, …). Esos
  gobiernan el **módulo** "Asistente IA" (menú Módulos → `/ia`), no el robot.
- El **robot JARVIS** (`IaChatFloat.vue`) se muestra solo con `usar-ia-chat`
  (`master.blade.php:80` + rutas `/ia/chat`, `/ia/suggestions`, `/ia/asistente/*` en `routes/web.php`).
  Ese permiso no estaba en `constants.js`, así que solo aparecía perdido en la pestaña dinámica
  "Otros / Sin categorizar" como "Usar ia chat".

Verificado en dev (solo lectura): Diana (id 3) y OSCAR (id 3811) tienen los 12 `ia_*` directos,
pero `can('usar-ia-chat') = false` → ven el menú `/ia` (sidebar renderizado con sus 6 enlaces) pero
no el robot. Nadie tiene `usar-ia-chat` directo; solo lo traen los roles `super-administrator` y
`DESARROLLADOR`.

**Arreglo (solo frontend, `rol/helper/constants.js`):** `usar-ia-chat` entra a la pestaña IA con su
propio acordeón "JARVIS — robot asistente flotante" (etiqueta "Usar JARVIS (robot asistente
flotante)"), y el acordeón de los `ia_*` se renombra a "Asistente IA (menú Módulos → Asistente IA)".
Al quedar curado, deja de salir en "Otros". Aplica igual al editor de roles y al de usuarios
(ambos leen `constants.js`). Sin cambios de BD ni de permisos asignados.

Verificado con una simulación de `constants.js` en node: el acordeón JARVIS agrupa solo
`usar-ia-chat`, el de Asistente IA los 12 `ia_*`, y `buildUncategorizedTab` ya no lo lista.

**Pendiente para que se vea:** integrar la rama `fix/permisos-ia-jarvis` a main y `npm run prod`
en `/var/www/megaisp`. Luego, a quien deba ver el robot, marcarle la casilla nueva.

## 2026-10-14 — Cambio de enfoque: pestaña propia "Asistente flotante"

A pedido del usuario, en vez de meter `usar-ia-chat` dentro de la pestaña IA, va en una **pestaña
propia "Asistente flotante"**, justo debajo de "IA" en la lista de pestañas, para distinguir uno de
otro. Casilla: "Usar asistente flotante (JARVIS)". La pestaña IA queda exactamente como estaba
(acordeón "Asistente IA" con sus 12 `ia_*`, sin renombrar).

Verificado con la simulación en node: orden de pestañas `… WhatsApp | IA | Asistente flotante |
War Room …`, la pestaña nueva agrupa solo `usar-ia-chat`, IA conserva sus 12 y "Otros" ya no lo
lista. El modal (`PermissionAssignmentModal.vue`) recorre `extraModuleTabs`/`fieldsJson` de forma
genérica, así que no requirió cambios.
