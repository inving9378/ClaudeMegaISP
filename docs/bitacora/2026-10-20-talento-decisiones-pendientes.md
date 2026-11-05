## 2026-10-20 — Talento: los 7 pendientes del barrido de pagos (7 ramas, sin mergear)

Respuesta a los pendientes del 19-oct (`2026-10-19-talento-demo-y-barrido-pagos.md`). Cada
punto en su rama, todas desde `main` (`bbb5f76d`), sin mergear ni pushear. Probadas juntas en
una rama temporal servida en `:8097` (ya borrada).

| Rama | Qué cambia |
|---|---|
| `fix/talento-apelaciones-admin` | Resolver apelación ya no exige ficha: el admin sin ficha firma como MEGAISP (`PenaltyService::firmanteDe`, reusado al aplicar y al confirmar). "Quien aplicó no resuelve" compara también a la persona (`penalties.created_by`). Columna nueva `talento_penalty_appeals.reviewed_by_user_id`; el modal dice "Resolvió: …" y las resueltas se abren con "Ver". |
| `fix/talento-asistencia-solo-lo-suyo` | Ver la asistencia/ubicación de todos queda para `talento.employees.view`, `talento.attendance.manage` o super admin; `talento.attendance.view` (lo tiene TECNICO) ya no basta. Aplica a lista, detalle, mapa en vivo y ruta. Ficha propia › Asistencia lleva botón a "Mi día" para entrar y salir. |
| `feat/talento-credenciales-carnet` | Credenciales muestra el carnet (frente, reverso, descarga) igual que Vendedores; las listas de gestión solo a quien gestiona. Sale de Información. De paso: el PDF leía `puesto->name` (es `nombre`) y la ruta `/talento/mi-ficha/{id}/pdf` no estaba en `route_permission` (un no-admin caía en el inicio). |
| `fix/talento-proyectos-reportar-avance` | El pool salía vacío para todos (`activities` vs `project_activities`). Botón "Reportar avance" con selector de actividad; participantes de `GET /proyectos/{id}/participantes` (uno mismo + equipo, o todos para staff); "Aprobar" en reportes pendientes para quien gestiona; botones de gestión ocultos al técnico; fechas "Invalid Date" corregidas. |
| `fix/talento-permisos-vendedor-mostrador-prod` | Migración `2026_10_20_110000_regrant_talento_permissions_to_vendedor_y_mostrador` (solo agrega, idempotente, roles por nombre). Vendedor: `talento.view`, `talento.ventas.view`, `talento.comisiones.view`. Mostrador: `talento.view`, `talento.work_orders.view/manage`, `talento.custody.view`, `talento.comisiones.view`. |
| `fix/talento-regla-semanal-cuenta-lo-que-se-paga` | La regla semanal del técnico cuenta con `LiquidationService::countBillableUnits()` (puntos validados en la ventana PayWeek), lo mismo que el pago. Etiquetas "Puntos validados". |
| `feat/talento-penalizaciones-pantalla-supervisor` | Portal › Supervisión › "Penalizaciones": por confirmar del equipo directo + aplicadas 60 días; confirmar reusa `confirmarPenalizacion()`. Admin: pestaña "Por confirmar" en `/talento/penalizaciones`. |

`fix/talento-regla-semanal…` y `feat/talento-penalizaciones…` suben `ASSET_VER` a '30' las dos
(mismo cambio, mezclan sin conflicto): conviene mezclarlas juntas.

**Verificado** (Playwright, 40 comprobaciones contra la rama junta + humo de ayer 9/9):
apelación resuelta por admin sin ficha y bloqueada para quien la aplicó (5 casos en tinker con
rollback, incluido "dos admins sin ficha"); supervisor confirma desde el portal y desaparece de
la cola del admin; técnico sin equipo no ve la sección (403); técnico entra y sale en Mi día y
solo ve su asistencia, no la de otro (403 en detalle y ruta); carnet y PDF para vendedor y
técnico, 403 al PDF de otro; técnico reporta avance eligiendo actividad, queda Pendiente, el
admin lo aprueba; migración de permisos probada con rollback (segunda corrida no cambia nada);
regla: con OTs validadas el número de la regla es igual al que cuenta el pago.

**Datos de prueba**: admin temporal, proyecto, evaluaciones de la semana 3→10 oct, la
penalización que confirmó el supervisor (con su cargo), la apelación y la asistencia de prueba
se borraron. La penalización #1 volvió a "applied".

**Migración aplicada en dev antes del merge**: `2026_10_20_100000_add_reviewed_by_user_id_to_talento_penalty_appeals`
(columna nullable) se corrió con `--force-uncommitted` para poder probar. Si la rama no se
mezcla, revertirla con `php artisan migrate:rollback --path=app/Modules/Addons/Talento/migrations/2026_10_20_100000_add_reviewed_by_user_id_to_talento_penalty_appeals.php`.

**Para decidir con estos cambios**
- Los pisos y techos de las reglas de técnico ahora se leen en puntos (una instalación = 9).
  Las reglas demo (36/45) quedan en 4 y 5 instalaciones por semana; hay que poner los valores
  reales. Lo que no esté validado al cierre cuenta 0, igual que en el pago.
- Causa de fondo de los permisos perdidos sin confirmar en prod: guardar un rol desde
  Administración → Roles reemplaza la lista completa de permisos; si vuelve a pasar, la
  migración no los devuelve otra vez.
- TECNICO también perdió `talento.comisiones.view` en la copia de prod; no se tocó (no se pidió).

## 2026-10-20 16:10 — Mezcladas a main las 7 ramas

Merges `3d10a609`, `f774bb11`, `8662b7fa`, `a6d68405`, `2aebba37`, `a0d65ae2` y `85d06917` en
`/var/www/megaisp` (verificado `main` antes, sin conflictos). `php artisan migrate`: corrió
`2026_10_20_110000_regrant…` (log: Vendedor +`talento.comisiones.view`; Mostrador
+`talento.custody.view`, +`talento.comisiones.view`); la de `reviewed_by_user_id` ya estaba.
`route:clear` + `view:clear` + build de producción del frontend. **Sin push a origin.**

Humo en dev real: técnico solo ve su asistencia (403 a la de otro), "Puntos validados" en Mi
progreso, botón a Mi día en su ficha, Proyectos sin 403, carnet + PDF del vendedor, sección
Penalizaciones del supervisor, y la prueba de ayer (portal, penalizaciones, liquidaciones y las
pestañas del vendedor) completa.

**Hallazgo en el humo (rama `fix/ultima-ruta-solo-html`, sin mergear):** `TrackLastVisitedRoute`
guarda la "última ruta" de cualquier GET 2xx sin extensión, también los PDF. Tras abrir
`/talento/mi-ficha/38/pdf`, el siguiente login de `demo_vendedor` iba directo al PDF (Playwright
lo veía como login fallido). Pasaba igual con `/vendedores/{id}/pdf` para admins. Arreglo: solo
guarda respuestas `text/html`; probado (login cae en pantalla, el PDF no cambia la última ruta,
una pantalla normal sí). `demo_vendedor.last_visited_route` se devolvió a
`/talento/colaborador/38`.

## 2026-10-20 16:30 — Mezclada `fix/ultima-ruta-solo-html`

Merge `3d18c09a` en `main` (verificado `main` antes). Solo PHP, sin build. Probado en dev real:
el vendedor entra a su ficha, abrir el PDF de la credencial no cambia su última ruta y una
pantalla normal sí se guarda. Sin push a origin.
