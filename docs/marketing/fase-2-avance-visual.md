# Fase 2 — avance de la revisión visual (agente-calidad-visual)

Revisión de solo lectura de las pantallas actuales de Marketing contra Clientes. No se corrige código.

15:01 — arranque: rol leído (15 puntos), patron-clientes.md y §2.11–§2.13 leídos. Datos de vista previa en `megaisp_test` (bajo `flock`): usuarios `mkt_prev_*`, 3 campañas PREV-F2, 3 leads y 1 formulario PREV-VIS, 1 cliente «PREV-VIS Cliente PRUEBA», filas de menú lateral de Marketing y Clientes. Servidor propio en 127.0.0.1:8097 (megaisp_test) · sigue: capturas de 22 pantallas de Marketing + 4 de Clientes, 1440/375 × claro/oscuro
15:12 — capturas listas: 22 pantallas de Marketing + 4 de Clientes (listado, ficha, panel, alta) × 1440/375 × claro/oscuro = 104 capturas, más el menú lateral (claro/oscuro, clic en «Marketing», celular). Medidas automáticas por captura (título, tarjetas, pestañas, tabla, botones, colores, desborde) · sigue: calificar los 15 puntos
15:25 — calificación hecha; se redacta fase-2-revision-visual.md · sigue: limpieza de megaisp_test y apagar servidor
15:30 — CIERRE. Entregado `fase-2-revision-visual.md`: NO SE CIERRA (13 de 15 puntos fallan; pasa el 12 y el 3 en la mayoría). Limpieza de megaisp_test hecha bajo flock (1 cliente, 3 leads, 1 formulario, 2 filas de menú, 3 campañas, 20 piezas, 2 nichos, 2 usuarios); servidor 8097 apagado; clave de vista previa borrada. Sin commits ni cambios de código.

## Segunda revisión (tras agente-diseno)
15:59 — arranque de la 2.ª revisión. Datos de vista previa otra vez en megaisp_test (bajo flock). Permisos TEMPORALES creados y dados solo a `mkt_prev_admin`: view-marketing-campaigns, test-voices, manage-integrations y, al ver un 403 en `/api/marketing/niches`, también create-/delete-marketing-campaigns, manage-marketing-niches y regenerate-marketing-variants; además se le dieron directo view-publishing-dashboard y publish-content (ya existían). Todo se borra al final. Servidor 8097 · sigue: 23 pantallas (se suma Integraciones) × 1440/375 × claro/oscuro en `scratchpad/visual/fase-2-r2/`
16:03 — textos de la API revisados (26 GET): etiquetas en español («Texto para Facebook/Instagram», «Guion de Reel», estados). Único texto en inglés: el 403 de Spatie «User does not have the right permissions.» cuando falta un permiso (lo devuelve el paquete, no Marketing)
16:15 — 23 pantallas recapturadas (108 capturas + recaptura de 01–06 con todos los permisos). Revisando composiciones a 1440 y 375 · sigue: oscuro, menú lateral, calificación
16:29 — 2.ª revisión calificada y escrita en fase-2-revision-visual.md (sección «Segunda revisión»); índice en scratchpad/visual/fase-2-r2/indice.html · sigue: limpieza
16:29 — CIERRE 2.ª revisión: NO SE CIERRA (13 de 22 pantallas de Marketing pasan los 15 puntos; quedan tablas, Leads, Voces e Integraciones del Hub). Limpieza en megaisp_test bajo flock: 7 permisos temporales y sus 7 asignaciones borrados, 1 cliente, 3 leads, 1 formulario, 2 filas de menú, 3 campañas, 20 piezas, 2 nichos y 2 usuarios. Servidor 8097 apagado, clave borrada. Sin commits ni cambios de código.

## Tercera revisión
16:46 — arranque de la 3.ª revisión: mismos datos de vista previa y los mismos 7 permisos temporales (solo mkt_prev_admin, bajo flock). Servidor 8097 · sigue: 22 pantallas × 1440/375 × claro/oscuro en scratchpad/visual/fase-2-r3/
16:57 — botón «Abrir Integraciones» verificado en Brand Kit, Voces y Canales (1440 y 375): btn-outline-secondary btn-sm, ícono fa-external-link-alt (existe en el CSS servido), abre /integraciones en pestaña nueva y la de Marketing se queda. API: /niches ya responde 200 con los permisos; sin textos en inglés · sigue: capturas en curso
17:01 — 3.ª revisión calificada: APROBADA (0 fallas en los 15 puntos, 22 pantallas). Índice en scratchpad/visual/fase-2-r3/indice.html · sigue: limpieza
17:01 — CIERRE 3.ª revisión: APROBADA. Limpieza de megaisp_test bajo flock: 7 permisos temporales y 7 asignaciones, 1 cliente, 3 leads, 1 formulario, 2 filas de menú, 3 campañas, 20 piezas, 2 nichos, 2 usuarios. Servidor 8097 apagado, clave borrada. Sin commits ni cambios de código.

## Cuarta revisión
18:30 — arranque de la 4.ª revisión (un solo botón principal por pantalla; Público solo «Tipo de público»; fecha fin validada). Mismos datos y permisos temporales bajo flock; servidor 8097 · sigue: 22 pantallas en scratchpad/visual/fase-2-r4/ + prueba del asistente
18:36 — capturas de la 4.ª revisión en curso (22 pantallas + ficha de campaña vacía como estado extra); la prueba del asistente espera a que terminen (el servidor no da abasto con las dos corridas)
18:49 — 4.ª revisión calificada: NO SE CIERRA (P5: ficha de campaña con dos botones rellenos; P10: aviso de fecha cortado y «MXN» cortado a 375). Asistente probado en navegador: validación de fechas y Público OK · sigue: limpieza
18:49 — CIERRE 4.ª revisión. Limpieza de megaisp_test bajo flock (7 permisos temporales + asignaciones, cliente, leads, formulario, filas de menú, campañas, piezas, nichos, usuarios); servidor 8097 apagado; clave borrada. Sin commits ni cambios de código.
