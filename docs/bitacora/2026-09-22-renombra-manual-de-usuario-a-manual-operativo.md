# Renombra "Manual de Usuario" a "Manual Operativo de Meganet"

## 2026-09-22 17:00 — Último ajuste de nombre, tras mover Talento a /manual

Irving notó que, al mover el capítulo Talento a `/manual`, esa pantalla se quedó con
su nombre original ("Manual de Usuario - MegaISP") — no el nombre que había pedido
antes ("Manual Operativo de Meganet"). Confirmado por pregunta directa: el cambio es
solo en `/manual` (`addon-manual`); `/empresa/manual` (`addon-empresa`) se queda tal
cual está, aunque eso signifique que ahora **los dos módulos comparten el mismo
nombre visible** — decisión explícita de Irving, no un descuido de esta vuelta.

### Cambios (solo el nombre — mismo módulo, misma ruta /manual)

- `ManualIndex.vue`: el encabezado de la pantalla ("Manual de Usuario - MegaISP" →
  "Manual Operativo de Meganet") y el texto de bienvenida cuando no hay nada
  seleccionado.
- `index.blade.php`: `<title>` de la pestaña del navegador.
- `module.json` del addon: `name`, `admin_cards[0].title` (la tarjeta en
  `/admin/administracion`) y las descripciones de sus 2 permisos.
- Migración nueva (`2026_09_22_170000`) que actualiza el nombre ya sembrado en
  `module_registry` para el slug `addon-manual` — la migración que originalmente
  registró el addon ahí solo otorga permisos, no toca `module_registry`, así que el
  nombre venía de otro punto de alta; se corrige con una migración nueva en vez de
  editar la ya corrida.

### Verificación
`php -l`/JSON válido en todo lo tocado.
