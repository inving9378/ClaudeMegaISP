## 2026-10-13 12:50 — Fase 4/5: aislamiento de datos por rol (resultado: ya estaba bien, sin cambios de código)

### Qué se probó (IDOR/aislamiento, con cuentas de prueba reales, en vivo)

Se crearon 2 técnicos (uno sin relación, uno supervisado por un tercero), 2 vendedores y 2
mostradores de prueba, y se atacaron directamente — vía API cruda (`fetch`, cookies de sesión
reales) y vía URL — los puntos donde un colaborador podría llegar a ver el dato de otro:

| Intento | Resultado | Esperado |
|---|---|---|
| Técnico A → progreso de Técnico B (sin relación), `/talento/api/colaboradores/{B}/progreso` | **403** | ✅ |
| Técnico A → ficha completa de B (`/talento/api/colaboradores/{B}`, requiere `talento.employees.view`) | **403** | ✅ |
| Técnico A → su propio progreso (control, debe funcionar) | **200**, con `regla_desempeno` incluido | ✅ |
| Supervisor → progreso de SU subordinado directo (B) | **200** | ✅ (esSuSupervisor) |
| Supervisor → "Mi equipo" | Solo lista a B (su subordinado real) — **no** aparece Técnico A (sin relación con él) | ✅ |
| Vendedor → abrir la ficha admin (`/talento/colaborador/{propia}`) | Redirigido en silencio a `/` (ni su propia ficha — Vendedor no tiene `talento.view`, solo usa el Portal) | ✅ (por diseño) |
| Vendedor A → `/talento/api/mis-ventas?seller_id=<de B>` (IDOR por parámetro) | 200, pero devuelve **exactamente los mismos datos** que sin el parámetro — el parámetro se ignora, el seller_id real sale siempre de la sesión | ✅ |
| Técnico → endpoint de Mostrador (`/talento/portal/mi-progreso-mostrador`) | **403** (permiso `payments_capture_manage` ausente) | ✅ |
| Mostrador → endpoint de Técnico (`/talento/portal/mi-calidad`) | **403** (permiso `talento.warranty.view` ausente) | ✅ |
| Vendedor → `/talento/api/mis-ventas?user_id=<de B>` | 200, mismo dato propio (parámetro ignorado) | ✅ |

### Verificado por lectura de código (sin repetirlo en vivo — mismo patrón ya confirmado arriba)

- **OTs/tareas de campo** (`OrdenTrabajoUnifiedService::detail()/iniciar()/completar()`,
  `resolveTask()`): CADA consulta trae `->where('colaborador_id', $colaboradorId)` o
  `whereHas('users', ...)` antes de tocar el `id` de la URL — un técnico no puede ver, iniciar ni
  completar la OT de otro ni por URL directa.
- **Documentos del expediente** (`documentoDetalle()/documentoPdf()/firmarDocumento()`): mismo
  patrón, `where('colaborador_id', $col->id)` — un docId ajeno responde 404 "Documento no
  encontrado" (ni siquiera revela que existe).
- **Custodia/material**: ya cerrado en una fase anterior (Fase A del Portal, documentado en
  CLAUDE.md como "IDOR cerrado").
- **Pantallas admin de alcance total** (`/talento/reglas-desempeno`, roster
  `/talento/api/colaboradores`): `talento.reglas_desempeno.view/.manage` y
  `talento.employees.view` están asignados **solo** a `super-administrator`/`DESARROLLADOR` — un
  técnico/vendedor/mostrador normal no puede abrirlas ni por URL (bloqueadas por el middleware de
  rutas antes de llegar al controller).

### Hallazgo de arquitectura (no es un bug — documentado para que quede claro)

`CheckRoutePermission` tiene **denegación por defecto**: una ruta que no aparece bajo NINGÚN
permiso en `config/route_permission.php` se bloquea sola (nunca "se cuela" por omisión). Algunas
rutas de Talento (ej. `/talento/api/colaboradores/{id}/progreso`) se registran deliberadamente
bajo un permiso **amplio** (`talento.view`, que sí tienen los técnicos) a nivel de middleware, y
dejan el candado fino "¿es tuyo, de tu subordinado, o tienes employees.view?" al controller —
diseño de dos capas ya documentado en el propio código (comentarios de David, 28-sep), y
confirmado aquí que funciona como se diseñó.

### Limpieza

Las 7 cuentas de prueba (2 técnicos, 2 vendedores con su `sellers`, 2 mostradores, el supervisor)
borradas al cierre, confirmado en cero.

### Estado

Fase 4 cerrada. **Sin cambios de código** — todo lo probado, en vivo y por lectura, sostiene el
aislamiento esperado: cada rol solo ve su propia parte, un supervisor solo ve a su equipo real
(nunca a quien no supervisa), y los intentos de manipular parámetros (`seller_id`, `user_id`) se
ignoran en vez de filtrar datos ajenos. Sigue la Fase 5 (extender Mostrador a "admin-lite": crear
tareas, trabajos de campo, aplicar pagos — sin llegar a tener todos los permisos de admin).
