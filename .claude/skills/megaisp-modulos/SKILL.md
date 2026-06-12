---
name: megaisp-modulos
description: Arquitectura modular y estándares de frontend de MegaISP. Usar al crear o modificar módulos (Core o Addons), vistas Blade, componentes Vue/Quasar, navegación SPA o plantillas de documentos.
---

# Arquitectura modular y frontend MegaISP

## Módulos
- Estructura: `app/Modules/Core` + `app/Modules/Addons`, registro en tabla `module_registry`.
- Todo módulo se entrega COMPLETO: lógica, relaciones, vistas, controladores, modelos y migraciones. Sin placeholders ni TODOs.
- Seguir el contrato de módulos v0.9 vigente en el repo.
- Plantillas de documentos viven en el módulo Plantillas (filas en BD), NUNCA como Blade sueltos.

## Frontend
- Stack general: Vue 3 + Quasar UMD sobre Blade. Excepción: módulo Flotas usa Bootstrap 5 + bootstrap-icons + Leaflet vía npm (NO Quasar, NO CDN).
- Navegación SPA: interceptor `spa-nav.js` (fetch-then-swap). Respetar `data-spa-skip` y la blacklist de vistas con scripts propios. No romper este gate global.
- Permisos en Vue: usar `store.state?.permissions` como computed reactivo (el getter `store.getters['permissions']` NO existe).
- Vue props: tipar correctamente (`ref(0)` para numéricas, no `ref(null)`). Cuidar z-index en modales.
- Leaflet en `onMounted` con `v-if` requiere `await nextTick()`. Respuestas Axios con null-checks (`data?.x?.length`).
- Estética tipo Splynx: layout limpio, sidebar de módulos, top bar, KPI cards. Tema light por defecto con toggle dark (preferencia por administrador).
