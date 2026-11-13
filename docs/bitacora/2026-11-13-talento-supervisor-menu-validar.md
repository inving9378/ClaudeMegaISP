## 2026-11-13 — Talento: el supervisor llega a «por validar» desde el menú

**Qué se veía:** al entrar como supervisor de equipo (permiso de validar órdenes, sin el de crearlas) el
menú de Talento no traía «Órdenes de trabajo»: había que escribir la dirección `/talento/ordenes` para
llegar al botón «N por validar». Y en la lista, el botón «Validar» de cada orden terminada solo lo veía el
personal administrativo; el supervisor tenía que abrir la orden con «Revisar» para encontrarlo.

**Qué pasa ahora:**
- El enlace «Órdenes de trabajo» sale también a quien puede validar y ver órdenes (`talento.work_orders.validate`
  + `talento.work_orders.view`). Un técnico sin permiso de validar sigue sin verlo.
- En la lista, «Validar» sale en las órdenes terminadas también para quien tiene el permiso de validar, igual
  que en el detalle. El servidor sigue revisando permiso y estado al validar.

**Comprobado:** se armó el menú como cada cuenta de prueba de dev: el supervisor de prueba y el administrador
de prueba ven el enlace; el técnico de prueba, no.

**Archivos:** `resources/views/module-sidebar/talento.blade.php`, `resources/js/components/module/talento/TalentoOrdenes.vue`.
