## 2026-10-09 — "Mi progreso" directo en la ficha de Talento, sin ir al Portal

### Contexto

Corrección de Irving sobre lo construido ayer: "Mi progreso" (calidad, garantías, mapa del
técnico externo) vivía SOLO dentro del Portal de Colaborador (`/talento/portal`), accesible desde
la ficha únicamente a través de la pestaña "Mi trabajo (Portal)" (un iframe). El pedido: que
aparezca directo en el dashboard de Talento que un técnico ya ve al entrar, sin tener que ir al
Portal. Eligió, entre dos opciones, que se muestre dentro de la pestaña "Información" (la primera,
sin clics extra) — no reemplazar la pestaña del Portal (esa sigue sirviendo para Mi día/Mi
dinero/Mi material, que no se tocaron).

### Diseño

- Backend nuevo: `TalentoColaboradorController::progreso($id)` — mismo candado que `ficha()`
  (uno mismo, su supervisor directo, o `talento.employees.view`), reusa
  `DashboardService::calidadTecnico()`/`mapaTecnico()` TAL CUAL (cero lógica de cálculo nueva),
  pero parametrizado por el colaborador DE LA FICHA, no por `auth()->user()` — por eso no se
  reusó el endpoint del Portal directo (ese es siempre self-scoped). Solo responde datos si el
  colaborador de la ficha tiene rol técnico (`es_tecnico:false` para los demás, el frontend no
  pinta nada).
- Ruta agregada a `config/route_permission.php` bajo la clave `talento.view` (la misma que ya
  cubre `/talento/colaborador/{id}`) — **lección aplicada de la tarea anterior de hoy** (el bug
  de Mostrador fue justo por una ruta nueva sin registrar ahí): se verificó con navegador real
  antes de dar por cerrado, no solo que el código "se ve correcto".
- Frontend nuevo: `TalentoFichaCalidad.vue` — puerto NATIVO (no iframe) del mismo contenido de
  "Mi progreso" del Portal, pero con Chart.js/Leaflet vía `import` de npm (como ya hace
  `TalentoVentas.vue`/Flotas/Mapa de Red) en vez del UMD global que usa el Portal (son apps
  distintas, cada una con su forma de cargar las librerías — no se cambió la del Portal, que
  sigue funcionando igual). Mismo estilo visual que el resto de la ficha (`card border-0
  shadow-sm`, no el estilo Quasar del Portal).
- Enganchado en `TalentoColaboradorFicha.vue`, dentro del bloque de la pestaña "informacion",
  debajo de `talento-ficha-informacion`, gateado por el `esTecnico` que el propio componente ya
  calculaba (del colaborador MOSTRADO, no de quien mira — así también le sale a un admin/
  supervisor viendo la ficha de un técnico, consistente con cómo se gatean las demás pestañas
  técnicas de este mismo componente, como "Calidad de caja"/"Rutas planta").

### Verificación

- `npm run dev` compiló sin errores nuevos.
- Navegador real (Playwright), técnico de prueba con 2 OTs sintéticas (1 pendiente sobre un
  cliente con geodata real, 1 completada): entra → cae en su ficha → pestaña "Información" YA
  trae "Mi progreso" sin ningún clic — KPIs correctos (2 asignados / 1 completado / 100% sin
  incidencias), donut y gráfica de garantías dibujados, mapa "Dónde debo ir hoy" con el pin real
  (es técnico de track externa), lista de trabajos recientes con sus 2 OTs. Sin errores de
  consola nuevos (el 401/403 de `/permissions-auth` y "documentación" es ruido preexistente del
  arnés de prueba, documentado en sesiones previas).
- Colaborador de prueba SIN rol técnico (Mostrador) → confirmado que Mostrador no tiene
  `talento.view` en absoluto (no llega a ninguna ficha, comportamiento preexistente, ajeno a este
  cambio) — el candado real de "solo técnicos" vive en el propio backend (`$esTecnico` por rol
  real del colaborador de la ficha), independiente de quién mire.
- Datos de prueba (2 usuarios, 2 colaboradores, 2 work orders) borrados y confirmados en cero.
  Ningún cliente/dato real tocado (solo se leyó geodata ya existente de clientes 17/19).

### Estado

Mergeado a `main`. La pestaña "Mi trabajo (Portal)" sigue existiendo tal cual (Mi día, Mi dinero,
Mi material, documentos) — solo "Mi progreso" quedó duplicado de forma nativa en "Información"
para que no haga falta ir al Portal a verlo.

### Fuera de alcance

- No se tocó el Portal de Colaborador (`public/talento-portal/app.js`) — sigue teniendo su propia
  versión de "Mi progreso" para quien SÍ entra por ahí (ej. desde el celular).
- Vendedor/Mostrador no tienen un "Mi progreso" nativo equivalente en su ficha todavía — solo se
  pidió para técnico.
