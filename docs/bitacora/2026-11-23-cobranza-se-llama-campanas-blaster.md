# 2026-11-23 — «Cobranza» pasa a llamarse «Campañas/Blaster»

## Pedido
Irving: el módulo ya no se llama Cobranza; ahora es «Campañas/Blaster» (también lanza avisos, anuncios y cortes
por zona, no solo cobranza). Primero el nombre; lo demás lo indica después.

## Lo que se hizo (solo el nombre que se ve)
- Menú lateral: `module_sidebar_config.sidebar_label` (migración `2026_11_23_120000_renombra_cobranza_a_campanas_blaster`,
  respeta una etiqueta personalizada), el seeder y el respaldo del blade `module-sidebar/cobranza-blaster`.
- `module.json`: nombre, menú, tarjeta de Administración y sección de Configuración («VoIP / SIP — Campañas/Blaster»);
  `module_registry.name` por la misma migración.
- Pantallas: título y migas de Campañas y de Configuración VoIP; encabezado «Campañas/Blaster».
- Roles: grupo de permisos «Campañas/Blaster» y «Ver campañas».
- IA: el nombre de la tarea de voz («Voz de las llamadas de Campañas/Blaster»).

## Lo que no se tocó a propósito
- Lo interno: carpeta y slug `addon-cobranza-blaster`, rutas `/cobranza/*`, permisos `cobranza.*` (roles ya
  asignados), tablas `cobranza_*`.
- El tipo de campaña «Cobranza (a morosos)» y el propósito de troncal «Saliente: Cobranza»: son un tipo, no el módulo.
- El grupo «Cobranza» de la IA: es la llave de las asignaciones generales (`IA::claveGeneral`); cambiarlo soltaría
  la llave asignada en prod.

## En prod
Con la versión: la migración cambia la etiqueta del menú; el resto sale en el código (los textos de roles y el
encabezado de Campañas van en el frontend compilado).
