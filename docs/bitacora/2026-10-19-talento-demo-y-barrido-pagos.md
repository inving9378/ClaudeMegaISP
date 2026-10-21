## 2026-10-19 07:30 — Talento: cuentas demo recreadas + barrido de pagos/penalizaciones/bonos con Playwright

### Por qué no existían las cuentas demo

El 2026-10-15 se cargó el dump de producción encima de la BD de dev
(`docs/bitacora/2026-10-15-restauracion-prod-en-dev.md`). Eso borró todo lo que se armó el
13 y 14 de octubre en datos: las 5 cuentas `demo_*`, las 8 reglas de desempeño semanal, las
reglas de compensación (sueldo base), los tipos de penalización de cuota semanal y la ficha de
MEGAISP. El código sí sigue en `main`; lo que se perdió fueron filas de BD.

### Qué se recreó (solo BD de dev, sin código)

Mismo diseño que el 14-oct, sacando los valores del respaldo previo
`/var/backups/mysql/megaisp-202610151227.sql.gz`, con dos ajustes:

- **Semana:** la última cerrada hoy, `PayWeek::lastCompleted()` = 10-oct → 17-oct (antes era
  3 → 10 oct). Datos fechados el 13-oct 12:00.
- **Rol técnico:** `TECNICO`. En esta BD (copia de prod) no existen `TECNICO_PLANTA` ni
  `TECNICO_INSTALADOR`, así que las 2 reglas de esos roles no se recrearon; la regla demo de
  calidad de señal quedó para `TECNICO`.

| Cuenta | Datos de la semana | Regla de sueldo | Resultado de las reglas semanales |
|---|---|---|---|
| `demo_tecnico_bajo` | 10 OTs completadas, 4 instalaciones con pérdida > 1 dB | Técnicos $2,000 | 2 penalizaciones $200 |
| `demo_tecnico_medio` | 40 OTs, 1 instalación fuera de rango | Técnicos $2,000 | Cumple / cumple |
| `demo_tecnico_alto` | 50 OTs, 0 fuera de rango | Técnicos $2,000 | Bono $300 + bono $250 (al ledger) |
| `demo_mostrador` | 18 pagos capturados | Mostrador $1,500 (demo) | Cumple |
| `demo_vendedor` | 15 prospectos, 5 ventas | Vendedores $1,500 (demo) | Bono $400 (al ledger) |

Todas con la misma contraseña, que **no** se escribe aquí (repo público): se le dio al usuario
en la sesión. No se reusó la del 14-oct porque dev ahora tiene la copia de clientes reales de
producción y es accesible desde internet.

Se crearon además 1 cliente demo ("CLIENTE DEMO — Talento") y 5 clientes de venta demo
("CLIENTE DEMO VENTA n") con un servicio personalizado de $300. La evaluación semanal se corrió
**solo** para los 5 demo (mismo servicio que el cron), no para los colaboradores reales.

Permisos: en esta BD los roles **Vendedor y Mostrador no traían** los permisos de Talento que
dieron las migraciones del 10, 13 y 14 de octubre, aunque las migraciones figuran como
corridas. Se volvieron a dar en dev con la misma lógica de esas migraciones (aditivo,
`givePermissionTo`).

## 2026-10-19 09:10 — Resultado del barrido con Playwright

Scripts de Node con el chromium de `node_modules/playwright`, entrando por el login real. Cuentas
temporales usadas y borradas al cierre: `qa_talento_admin` (DESARROLLADOR sin ficha) y
`qa_talento_tec_libre` (técnico sin supervisor). El supervisor temporal se convirtió en
**`demo_supervisor`** (jefe directo de `demo_tecnico_bajo`, colaborador #39), porque quedó
registrado como quien confirmó una penalización real del demo.

### Funciona (verificado en pantalla o contra la API real con la sesión de cada rol)

- Reglas semanales por rol: técnico (cantidad y calidad de señal), mostrador y vendedor dan
  penalización / cumple / bono según lo definido. Los bonos se abonan solos al ledger.
- Confirmar penalización: el técnico no puede confirmarse a sí mismo (403); el supervisor solo
  confirma las de su equipo (403 con quien no supervisa); el admin sin ficha confirma desde
  `/talento/reglas-desempeno` y queda a nombre de MEGAISP; confirmar dos veces da 422.
- El descuento cae en la semana evaluada (10 → 17 oct) y entra al cálculo.
- Liquidaciones calculadas desde la pantalla: bajo $1,600 (2,000 − 200 − 200), medio $2,000,
  alto $2,550 (2,000 + 300 + 250), mostrador $1,500, vendedor $1,900 (1,500 + 400). Recalcular
  3 veces no duplica el sueldo base. El buscador del modal y el filtro de la lista funcionan.
- Apelación: el técnico apela desde su ficha; si quien resuelve tiene ficha, al revocarla se
  abona el reembolso en la misma semana del descuento (total vuelve a $0).
- Ficha propia (técnico, mostrador, vendedor): ve su liquidación y su detalle, sin Calcular,
  sin buscador y sin "Cerrar liquidación".
- Aislamiento: nadie ve progreso, ficha, regla de sueldo ni liquidación de otro; el supervisor
  solo ve a su equipo; cambiar `seller_id`/`user_id` en la URL no muestra datos ajenos.
- Mostrador "admin-lite": abre Órdenes, busca técnicos y crea una orden (borrada después); no
  ve ni calcula sueldos; abre Captura de pago y encuentra al cliente demo.

### Hallazgos (no corregidos; pendientes de decisión)

1. **Producción: Vendedor y Mostrador sin los permisos de Talento.** En el dump del 15-oct las
   migraciones 2026_10_08/10_10/10_13/10_14 figuran como corridas pero `role_has_permissions`
   no tiene esas filas (ya había pasado el 10-oct). Causa probable:
   `RolController::update()` revoca todo permiso que el formulario de Roles mande en `false`, así
   que guardar un rol desde la pantalla borra lo que dieron las migraciones. Verificar en prod.
2. **Unidades distintas entre regla y pago.** La regla semanal del técnico cuenta OTs
   completadas (piso 36 OTs, por `created_at`); la liquidación cuenta **puntos** de OTs
   **validadas** (cuota 36 puntos = 4 instalaciones, por `validated_at`). La regla de sueldo se
   llama "cuota diaria" pero se aplica como semanal. Con OTs validadas, el técnico bajo quedaría
   penalizado por poca producción y a la vez cobraría sobreproducción.
3. **Ningún admin puede resolver apelaciones.** `TalentoPenaltyController::resolveAppeal()`
   exige ficha propia y ningún admin la tiene (422 "No tienes perfil de colaborador"). Atribuirlo
   a MEGAISP choca con la regla "quien aplicó no resuelve" cuando la confirmó otro admin sin ficha.
4. **Botón "Última semana completa"** (`TalentoLiquidaciones.vue::fillLastWeek`): el lunes 19
   llena 17-oct → 23-oct (la semana en curso, terminando en viernes); el backend la normaliza a
   17 → 24 oct, así que calcula la semana que todavía no cierra.
5. **El supervisor no tiene pantalla para confirmar** las penalizaciones de su equipo: la API lo
   permite, pero el único botón está en `/talento/reglas-desempeno`, que es solo de admin.
6. **"Mi equipo" en blanco para un admin sin ficha** (bug de la Fase 1, sigue abierto):
   `/talento/portal/mi-equipo-progreso` responde 403 "Sin perfil de colaborador activo".
7. Menores: "Mi progreso" muestra la semana como `2026-10-10T06:00:00.000000Z`; la tarjeta de
   penalización aplicada no muestra el monto; "Mi dinero → Cuenta" solo muestra la semana en
   curso (el colaborador no ve en el portal lo que se le liquidó la semana pasada); texto
   "mirá" (voseo); total negativo como "$-200.00"; varias pestañas de la ficha bloqueadas por
   permiso se ven como tablas vacías ("Sin registros") en vez de ocultarse.

No cubierto: el bono de salud de red por instalación (`HealthBonusService`) es otro sistema,
se activa al validar cada OT con datos reales de OLT/caja.

## 2026-10-19 09:38 — Arreglos de los hallazgos sencillos (4 ramas, sin mergear)

Hechos en el worktree `/var/www/megaisp-talento`, cada rama sale de `main`:

| Rama | Hallazgo | Qué cambia |
|---|---|---|
| `fix/talento-liquidaciones-ultima-semana` | 4 | El botón llena la última semana cerrada (sábado 18:00 → sábado 18:00, misma regla que `PayWeek::lastCompleted()`), en hora local. |
| `fix/talento-liquidaciones-negativos` | 7 | Liquidaciones muestra "-$200.00" en vez de "$-200.00". |
| `fix/talento-penalizaciones-aplicado-por` | 7 | La lista de penalizaciones carga quién la aplicó (antes "—"). |
| `fix/talento-portal-presentacion` | 6 y 7 | Mi equipo: el super admin sin ficha ve a todos (sin MEGAISP). Mi progreso: semana "10 oct al 17 oct" y monto de la penalización o bono en la tarjeta. "mira" sin voseo. Negativos "-$" en el portal. ASSET_VER 29. |

Verificado con Playwright contra una rama temporal que juntaba las 4 (se juntaron sin
conflictos), servida con `php artisan serve` desde el worktree: 15 de 15 comprobaciones bien,
incluidos supervisor (sigue viendo solo su equipo) y técnico sin equipo (sigue sin ver la
sección y el servidor le responde 403). La lógica de la semana se probó además con 7 fechas (sábado antes y después
de las 18:00, cambio de mes y de año). La rama temporal y la cuenta `qa_talento_admin` se
borraron; las 6 cuentas demo siguen.

**Al mergear:** las dos ramas de Liquidaciones son de la pantalla admin (Vue compilado): hay
que correr el build en `/var/www/megaisp` para que se vean. El portal no se compila.

**No se hizo el punto de las pestañas vacías:** la decisión del 28-sep (David) es que la ficha
propia muestre asistencia, custodia, academia, dispositivos y roles múltiples aunque el rol no
tenga el permiso de staff; lo que falla es que esas APIs siguen exigiendo ese permiso (403 para
Vendedor y Mostrador). Ocultarlas contradice esa decisión; abrir las APIs a "uno mismo" es un
cambio de permisos. Queda para decidir.

## 2026-10-19 10:20 — Pestañas de la ficha propia (decisión: que cada quien vea sus datos)

Rama `fix/talento-ficha-propia-pestanas` (sin mergear). Asistencia, Custodia, Dispositivos,
Roles múltiples y Academia ya cargan para Vendedor y Mostrador en su propia ficha:

- `config/route_permission.php`: esas lecturas pasan también bajo `talento.view`, la misma
  convención que el resto de la ficha propia. Custodia, dispositivos y roles ya revisaban en el
  controller "uno mismo, su supervisor o staff"; la Academia es self-scoped por usuario.
- `TalentoAttendanceController::data()/show()` no filtraban por persona: quien no tiene
  `talento.attendance.view` ahora ve solo lo suyo y lo de su equipo directo. Quien lo tiene ve lo
  mismo que antes.
- Ningún rol recibe permisos nuevos.

Verificado con Playwright (55 comprobaciones): Vendedor y Mostrador ven su asistencia (registro
temporal, ya borrado), su custodia, dispositivos, roles y el catálogo de la Academia, sin botones
de administración. No ven lo de otros (403 o lista vacía). Las escrituras que comparten
dirección siguen cerradas: vincular un dispositivo a otro, crear o editar cursos, registrar
entradas y editar asistencia. El técnico y el supervisor siguen igual.

**Hallazgos nuevos (sin tocar):**
- Quien tiene `talento.attendance.view` (el rol TECNICO lo tiene) ve **la asistencia de todos**
  en `/talento/api/asistencia`, con la ubicación de entrada; no es solo su equipo. Así estaba
  antes de este cambio. Cerrarlo le quita acceso a un rol existente, así que es decisión de
  Irving.
- Pestaña Credenciales de la ficha propia: solo muestra listas globales que son admin-only a
  propósito, y la del colaborador está cerrada a uno mismo (David, 28-sep) → siempre sale vacía.
- Pestaña Proyectos: el catálogo de tipos de actividad (`/talento/api/activity-types`) da 403 a
  técnicos, vendedores y mostradores; reportar avance de un proyecto necesita ese catálogo.
- En producción esto no ayuda a Vendedor y Mostrador hasta que se les devuelva `talento.view`
  (hallazgo 1 de la mañana).

## 2026-10-19 10:50 — Mezcladas a main las 5 ramas

Merges `18e842b7`, `4767f088`, `83e4e831`, `79042276` y `bbb5f76d` en `/var/www/megaisp`
(verificado `main` antes de mezclar, sin conflictos), seguidos del build de producción del
frontend (`npm-build.sh`, modo prod). Prueba de humo en dev real con las cuentas demo: 9 de 9
bien (portal, Penalizaciones, Liquidaciones con el bundle nuevo, las 5 pestañas del vendedor y
el aislamiento). **No se hizo push a origin.**
