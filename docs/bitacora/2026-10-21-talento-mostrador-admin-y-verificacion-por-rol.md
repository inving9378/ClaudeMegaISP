## 2026-10-21 — Talento: Mostrador administrador + verificación completa por rol

### Definiciones de hoy

- **La asistencia es para todos**: cualquiera con ficha activa entra y sale desde el portal
  (Mi día). Ya funcionaba así: el portal solo pide ficha activa y `portal.colaborador`. Sin ficha
  no hay asistencia: hoy Alondra (Mostrador/Vendedor) y manuel (Vendedor) no tienen ficha.
- **Mostrador = administrador de Talento sin dar de alta ni eliminar fichas**, solo dentro de
  Talento, sin acceso a roles ni permisos. Reemplaza el "admin-lite" del 13-oct (tareas, órdenes
  y captura de pago, sin ver dinero). El rol no tiene permisos del sistema para crear/eliminar
  usuarios ni para Administración → Roles; eso no cambia.

### Rama `feat/talento-mostrador-admin` (sin mergear)

1. `c3cd4e5f` — permiso nuevo `talento.colaboradores.create` (crear y editar ficha compartían
   `talento.manage`). `store()` y `usersDisponibles()` lo exigen; editar sigue con
   `talento.manage`; eliminar ya tenía `talento.colaboradores.delete`. Migración
   `2026_10_21_100000` lo da a los roles y cuentas que hoy tienen `talento.manage` (sin Mostrador),
   así nadie pierde el alta. Migración `2026_10_21_100100` da a Mostrador todos los `talento.*`
   menos crear y eliminar fichas (91 de 93). Colaboradores: botón "Nuevo" y "Editar" separados.
2. `0cb85b6e` — 35 direcciones de Talento no estaban en `route_permission`: solo el rol admin
   llegaba aunque otro tuviera el permiso (reglas de desempeño, apelaciones, tipos de
   penalización, puestos, niveles, escalafón, validar/cambiar estado de órdenes, materiales y
   exámenes de la academia, alertas de credenciales, documentos pendientes, ranking de ventas).
   Se ligan al permiso que revisa su controller. Niveles va con `levels.manage` (TECNICO tiene
   `levels.view` y crear un nivel no revisa nada más); el ranking de ventas con
   `employees.view` (Vendedor tiene `ventas.view`). Comparado main vs rama, rol por rol y cuenta
   por cuenta: solo cambia Mostrador (+30) y ADMINISTRADOR_COMPLETO (+5 de consulta que su rol ya
   tenía permitidas: alertas de credenciales, escalafón, roadmap). Vendedor y TECNICO igual.

Migraciones corridas en dev con `--force-uncommitted` para probar (rama sin mergear).

### Verificación (Playwright, rama servida en :8097, cuentas demo)

- Por rol (98/98): técnico bajo, alto, supervisor, vendedor y mostrador entran a una pantalla
  (no a una descarga); los cuatro roles registran entrada y salida en Mi día y cada uno ve solo
  su asistencia (supervisor: la suya y la de su técnico; mostrador: la de todos). Técnico y
  vendedor no ven progreso, regla de sueldo, liquidación ni custodia de otro, ni calculan pagos;
  cada uno ve su liquidación ($1,600 / $2,550 / $1,900 / $1,500). Mostrador abre las pantallas
  de administración, ve regla, préstamos, fondos, expediente y progreso de cualquiera, calcula
  liquidaciones (dan lo mismo y sigue una por persona), no tiene "Nuevo colaborador" ni
  "Eliminar" (403 en el servidor), sí edita; no entra a Roles ni a crear usuarios. El
  administrador conserva "Nuevo" y "Eliminar".
- Los 7 cambios del 20-oct (38/38): apelación resuelta por admin sin ficha, pestaña "Por
  confirmar", supervisor confirma en el portal, asistencia propia, carnet y PDF, avance de
  proyecto con actividad y aprobación, "Puntos validados".
- Arreglos del 19-oct y humo del vendedor: bien. Ruido conocido de `artisan serve` (logo en
  `/storage`, `permissions-auth` en el login).
- Regla = pago con órdenes validadas (transacción deshecha): 36/36, 45/45, 441/441; la orden
  validada después del corte del sábado 18:00 no cuenta en ninguna de las dos.

Datos de prueba borrados (asistencias, proyecto, evaluaciones 3→10 oct, penalización confirmada
y su cargo, apelación, admin temporal). Liquidaciones demo iguales; el sueldo base se reescribe
al recalcular (uno por persona).

### Para decidir

- Diana y OSCAR conservan "dar de alta fichas" por permisos directos de su cuenta (no del rol).
- Un Mostrador con ficha puede calcular y cerrar su propia liquidación (no hay candado de
  "nadie se calcula a sí mismo"; las penalizaciones sí lo tienen).
- Reglas de técnico en puntos: las evaluaciones demo del 10→17 oct se hicieron con la forma
  anterior (órdenes completadas). Con la regla nueva y sin órdenes validadas, los tres técnicos
  quedarían penalizados; validadas, los tres pasarían el techo (90/360/450 puntos). Faltan los
  valores reales.
- Un permiso `talento.*` nuevo no llega solo a Mostrador: hay que dárselo al rol.
