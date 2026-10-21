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
- Un permiso `talento.*` nuevo no llega solo a Mostrador: hay que dárselo al rol.

## 2026-10-21 — Decidido: la propia liquidación la cierra el admin

Rama `fix/talento-liquidacion-propia-la-cierra-el-admin` (sin mergear, sale de `main`): un
Mostrador con ficha no puede cerrar su propia liquidación; la cierra el administrador. `cerrar()`
responde 403 ("Tu propia liquidación la cierra un administrador.") si la liquidación es de la
ficha de quien lo pide, salvo super admin; lista y detalle traen `puede_cerrar` y el botón Cerrar
no aparece en la propia. Calcular la propia sigue permitido (queda en borrador).

Probado junto con `feat/talento-mostrador-admin` en una rama temporal (ya borrada), 11/11: el
Mostrador no ve Cerrar en su fila ni en su detalle y el servidor se lo niega; sí lo ve en las de
otros; el admin sí lo ve en la del Mostrador; el técnico tampoco cierra la suya. Ninguna
liquidación se cerró (las 5 demo siguen en borrador); admin temporal borrado.

## 2026-10-21 — Cuota diaria × 6 y regla de prueba del técnico en puntos

El piso y el techo no son un número del sistema: los define cada regla. Lo que estaba mal eran
los valores de la regla de prueba del técnico (36/45, puestos cuando se contaban órdenes).

**Hallazgo de dinero:** la pantalla de reglas de sueldo deja elegir periodo "Diario" (la regla
de prueba "Técnicos — cuota diaria (4 instalaciones o 12 garantías)" guarda 36), pero el pago
tomaba ese número como cuota de toda la semana: con las órdenes demo validadas el medio cobraba
$20,000 y el alto $25,550. Decisión: **una cuota diaria cuenta 6 días, lunes a sábado.**

Rama `fix/talento-cuota-diaria-por-6-dias` (sin mergear): `TalentoCompensationRule::cuotaSemanal()`
es el punto único (diaria × 6; cualquier otra igual que antes) para liquidación, desglose del
portal, tablero, puntuación, app y el $/unidad de la pantalla (que además muestra "= N por semana").
De paso el desglose del portal redondeaba antes de multiplicar y quedaba un centavo arriba del pago.

**Datos demo ajustados (solo dev, respaldo en el scratchpad de la sesión):** las 100 órdenes de
los tres técnicos demo validadas el 14-oct (dentro de la semana 10→17); regla de prueba #17 →
"DEMO — Técnico — puntos validados por semana", piso 216 (= cuota de pago: 4 × 6 × 9) y techo
405 (45 instalaciones); se borraron sus evaluaciones anteriores con su penalización y su bono y se
reevaluó la semana: bajo 90 pts → penalización (la confirmó el supervisor desde su pantalla),
medio 360 → cumple, alto 450 → bono $300.

Probado en la rama (19/19): la regla mide lo mismo que el pago (90/360/450) y las liquidaciones
dan bajo $1,600 (2,000 − 400), medio $3,333.33 (2,000 + 1,333.33 de sobreproducción), alto
$4,716.67 (2,000 + 2,166.67 + 550 de bonos); cada técnico ve su resultado en Mi progreso y su
monto en su ficha; la pantalla de sueldo muestra $9.26 por punto (2,000 ÷ 216). Mostrador $1,500
y vendedor $1,900 sin cambio. **Hasta mezclar, recalcular en el sitio de dev daría los montos
inflados.**
