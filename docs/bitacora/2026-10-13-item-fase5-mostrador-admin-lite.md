## 2026-10-13 13:40 — Fase 5/5 (final): Mostrador "admin-lite" — trabajos de campo

### Qué ya existía (verificado, no tocado)

- **Crear tareas**: Mostrador ya tenía `task_add_task`/`task_edit_task`/`task_archive_task`
  desde antes de esta sesión.
- **Realizar pagos**: Mostrador ya tenía `payments_capture_manage` (pantalla "Captura de pago",
  `/finanzas/captura-pago`) — confirmado con una prueba rápida que sigue cargando 200 para
  Mostrador.
- Lo único que faltaba, literal, era **"trabajos de campo"** — crear/asignar órdenes de trabajo
  (OTs) a un técnico.

### Hallazgo relevante antes de tocar nada — permisos directos de una cuenta real muy por encima de "admin-lite"

Al investigar por qué el código ya tenía un comentario diciendo "Diana puede crear órdenes para
un técnico" pero el ROL Mostrador no tenía `talento.work_orders.manage`, se confirmó que
**Diana (user real id=3) tiene ~35 permisos `talento.*` otorgados DIRECTAMENTE a su cuenta** —
incluyendo `talento.penalties.manage`, `talento.funds.manage`, `talento.loans.manage`,
`talento.settlement.manage` (finiquito), `talento.academy.manage`, `talento.escalafon.manage` —
es decir, prácticamente el admin completo de Talento, acumulado permiso por permiso en algún
momento no documentado, **nunca como política del rol**. Esto es mucho más amplio que el
"admin-lite" pedido ahora para el rol. **No se tocó** (revocar permisos de una cuenta real de
RRHH/finanzas es una decisión de Irving, no algo para decidir sola en esta fase) — queda
anotado para que se decida aparte si se quiere auditar/limpiar.

### Qué se otorgó (al ROL Mostrador, no a ninguna cuenta individual)

Migración `2026_10_13_120000_grant_talento_admin_lite_to_mostrador.php`, 3 permisos
deliberadamente acotados:

- `talento.view` — gate amplio de middleware. **No destapa por sí solo** compensación/
  penalizaciones/préstamos/finiquito/académia de nadie — cada una de esas pantallas tiene su
  propio candado en el controller (uno-mismo/su-supervisor-directo/permiso-de-staff-específico),
  verificado leyendo el código real de `config/route_permission.php` (patrón documentado y
  repetido ahí mismo por el equipo desde el 28-sep).
- `talento.work_orders.view` — abrir `/talento/ordenes`.
- `talento.work_orders.manage` — crear/asignar órdenes; `TalentoWorkOrderController::
  esStaffOrdenes()` lo trata como "staff" (ve/gestiona las de cualquier técnico, no solo un
  equipo supervisado — es lo que necesita un "dispatcher").

Cambio adicional en `config/route_permission.php`: se agregó `/talento/api/colaboradores` (el
buscador de técnicos que usa el formulario "Nueva orden") también bajo `talento.work_orders.manage`
— sin esto, Mostrador podía crear la orden pero no podía BUSCAR a quién asignársela (ese endpoint
seguía exigiendo `talento.employees.view`, que a propósito NO se le otorgó — ese permiso destapa
el roster completo con fichas de todos, demasiado amplio).

### Verificación en vivo

Cuenta de prueba `prueba_fase5_mostrador` (rol Mostrador limpio, sin el historial de permisos
directos de Diana) + `prueba_fase5_tecnico` (TECNICO_PLANTA):

1. `/talento/ordenes` carga (200) — el "Talento" apareció en su sidebar por primera vez.
2. Buscar al técnico de prueba por nombre en el selector de "Nueva orden" → aparece correctamente
   (`GET /talento/api/colaboradores?search=...` → 200, con el técnico en los resultados).
3. **Se creó una orden real** (`POST /talento/api/ordenes`, tipo "Instalación nueva") asignada al
   técnico de prueba → 201, quedó como `Task` tipo `campo` con el técnico correcto asignado
   (confirmado contra la tabla `tasks` directamente).
4. Mostrador intentó ver la regla de compensación del MISMO técnico
   (`/talento/api/colaboradores/{id}/regla`) → **sigue bloqueado (403)** — confirma que el límite
   "admin-lite" se sostiene: puede asignar trabajo, no puede ver/tocar dinero ni expediente.

### Limpieza

Las 2 cuentas de prueba + la orden/tarea sintética creada — borradas, confirmado en cero.

### Estado

Fase 5 cerrada — **última de las 5**. Mostrador ahora puede, con permisos otorgados al ROL (no a
una cuenta individual): crear tareas (ya tenía), capturar pagos (ya tenía) y crear/asignar
trabajos de campo a técnicos (nuevo), sin ganar visibilidad sobre compensación, penalizaciones,
préstamos, finiquito o expediente de nadie.

## Cierre de las 5 fases

1. Auditoría — un bug real (Mi equipo en blanco para admin sin ficha propia), documentado.
2. Reglas de desempeño — motor extendido a técnico/mostrador, 8 reglas reales creadas.
3. Verificación de cálculo — 2 defectos propios de la Fase 2 encontrados y corregidos antes de
   que importaran (límite de semana, tarjeta nunca conectada para técnico/mostrador).
4. Aislamiento por rol — todo correcto, sin cambios de código.
5. Mostrador admin-lite — trabajos de campo habilitado al rol; hallazgo colateral de permisos
   directos de una cuenta real muy por encima de lo pedido, anotado sin tocar.
