## 2026-10-08 15:05 — "Mi equipo", quinta pieza del dashboard por rol: visibilidad para supervisores y super admin

### Contexto

Mientras se mergeaban Vendedor y Mostrador, Irving pidió una pieza más: "el admin debe ver todos
esos datos de cada uno a cargo y el super admin todo". Es decir — las 3 pantallas de "Mi progreso"
(técnico/vendedor/mostrador) son todas self-scoped (cada quien ve solo lo suyo); esto agrega una
capa de supervisión encima, sin tocar ninguna de las 3.

### Diseño

Dos niveles, reusando precedentes que ya existían en el sistema:

- **Supervisor ve a su equipo directo** — mismo candado que ya usa
  `TalentoColaboradorController::miEquipo()` (decisión de David, 28-sep: "el supervisor debe poder
  controlar lo que hacen los trabajadores a su cargo"), basado en `talento_colaboradores.
  supervisor_id`. Rol-agnóstico a propósito: cualquiera con subordinados directos asignados ve su
  equipo, sin importar qué rol tenga.
- **Super admin ve a todos** — gateado por ROL directo (`super-administrator`/`Super Administrador`/
  `DESARROLLADOR`), NO por un permiso ancho como `talento.employees.view` (que hoy también carga el
  rol Mostrador por una razón ajena a esto — usarlo habría expuesto "ver todo" a cualquier
  Mostrador normal, no solo a los verdaderos administradores).

Por persona se muestra **un solo número**, no el dashboard completo de cada quien (sería
carísimo y ruidoso de listar a varias decenas de personas): técnico → completados del período;
vendedor → ventas del período; mostrador → pagos capturados del período. Mismo ícono/color por
track que ya usan las pestañas individuales.

### Hallazgo real (dato, no código): nadie en dev tiene supervisor_id poblado

`talento_colaboradores.supervisor_id` está en `NULL` para los 13 colaboradores activos reales —
el organigrama nunca se cargó. Esto significa que, hasta que Irving asigne supervisores reales,
el nivel "mi equipo" (no super-admin) saldrá vacío para todos — comportamiento honesto, no un
bug: en cuanto se pueble `supervisor_id` (vía el formulario de ficha que ya existe), empieza a
funcionar solo, sin tocar código.

### Hallazgo colateral (no corregido, fuera de alcance): TECNICO_PLANTA/TECNICO_INSTALADOR no pueden iniciar sesión

Al armar un colaborador de prueba con el rol `TECNICO_PLANTA` para probar "mi equipo", el login
falló con "Estas credenciales no coinciden con nuestros registros" pese a contraseña correcta.
Causa: `LoginController::STAFF_ROLES` (el allowlist de roles que pueden entrar al panel) incluye
`TECNICO` pero **no** `TECNICO_PLANTA` ni `TECNICO_INSTALADOR` — pese a que ambos sí tienen permisos
reales asignados en el sistema (`talento.warranty.view`, por ejemplo) y `Actor::trackPrincipal()`
los trata como variantes válidas de técnico. En dev no afecta a nadie real hoy (0 usuarios reales
con esos roles sin también tener `TECNICO`), pero es una fuga latente: si algún día alguien recibe
`TECNICO_PLANTA` o `TECNICO_INSTALADOR` como su ÚNICO rol técnico, quedaría sin poder entrar al
sistema. Se dejó constancia aquí en vez de corregirlo — es un candado de otro módulo (login),
ajeno al alcance de esta pieza.

### Qué se construyó

- `DashboardService::equipoProgreso(int $supervisorColaboradorId, bool $verTodo, int $dias=30)` —
  agrega una fila por colaborador del equipo (o de todos, si `$verTodo`), con su track y su
  métrica correspondiente.
- `Actor::esSuperAdminTalento()` / `Actor::tieneEquipo()` — nuevas facetas, mismo patrón que
  `esTecnico()`/`esVendedorTrack()`/`esMostradorTrack()`.
- Nueva entrada `mi_equipo` en `sections()`, bajo un grupo nuevo del menú: **"Supervisión"**
  (separado de "Mi trabajo" porque conceptualmente es distinto: no es trabajo propio, es
  visibilidad sobre el trabajo de otros).
- Endpoint `GET /talento/portal/mi-equipo-progreso` — el candado real vive en el propio query
  (ignora cualquier id que no sea el del actor en sesión) + `abort_unless` en el controller.
- Pestaña nueva en el Portal: lista con ícono/color por track, nombre, y el número + etiqueta de
  su métrica — mensaje distinto si está vacío por "sin equipo todavía" vs "no hay colaboradores".

### Verificación

- Super admin de prueba → ve "Mi equipo" con los **13 colaboradores reales** de dev, cada uno con
  su número real (Diana 409 pagos capturados, Ariana 124, Guadalupe 18 ventas, etc.) — confirmado
  en pantalla, sin tocar ni un registro real.
- Supervisor de prueba con 2 subordinados de prueba (uno técnico, uno vendedor) → ve **solo esos
  2**, con sus iconos/track correctos — confirma el aislamiento por `supervisor_id`.
- Colaborador de prueba sin equipo y sin rol de super admin → "Mi equipo" no aparece en el menú,
  y un `GET` directo al endpoint devuelve `403`.
- Todos los usuarios/fichas de prueba (incluidos los 2 subordinados sintéticos) borrados al final,
  confirmado por conteo en cero. Ningún dato real (incluido `supervisor_id` de un colaborador
  real) se tocó.

### Estado

Rama `feature/talento-portal-mi-equipo`, sin mergear a `main` todavía.
