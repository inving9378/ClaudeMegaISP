## 2026-10-21 — App de Talento 1.19: al día con lo definido del 13 al 21 de octubre

Pedido: reimplementar la app (React Native, `/home/meganet/TalentoEquipo`, última versión 1.18
del 7-oct) con lo definido en Talento. Decisión: el Mostrador usa la app como cualquier
colaborador; sus funciones de administrador quedan solo en la web.

### Revisión (parte 1, solo lectura)

La app no sabía quién era la persona: `colaboradorPayload()` leía columnas que la ficha no tiene
(nombre vacío, todos "technician"), así que todos veían las mismas pestañas (Órdenes, Cajas, Rutas,
Calidad también para vendedor y mostrador). Apelar desde la app guardaba la apelación a nombre del
usuario, sin fecha y sin pasar la penalización a "apelada". "Mi semana" proyectaba solo el libro de
cuentas (sin sueldo base ni sobreproducción) con la cuota global de técnico. Faltaban Mi progreso
(regla semanal) y la pantalla del supervisor para confirmar penalizaciones. El aislamiento por
persona ya estaba bien.

### Servidor de la app — rama `feat/talento-api-app-21oct` (sin mergear)

- Login y `/me`: nombre real, `tipo`/`track`, `es_tecnico`, `es_vendedor`, `es_mostrador`,
  `tiene_equipo` (de `Actor`, igual que el portal web).
- `GET /portal/progreso` (regla semanal propia o de alguien del equipo, `DashboardService::
  reglaDesempeno`), `GET /portal/equipo/penalizaciones`, `POST …/{id}/confirmar` y
  `GET /portal/equipo/progreso` (delegan en `PortalTecnicoController`).
- Apelar delega en `TalentoPenaltyController::submitAppeal`.
- Penalizaciones con estado en español, semana, quién aplicó y quién resolvió; solo se ofrece
  apelar una aplicada.
- `compensacion/semana` con `LiquidationService::breakdown()` (regla asignada) y solo los
  movimientos de la semana exacta (con "entre inicio y fin" entraban los de la semana anterior,
  que comparten el sábado de corte: el vendedor demo proyectaba $1,900 en vez de $1,500).
- Liquidaciones con fechas `Y-m-d` y Borrador/Cerrada; Información da el puesto desde el rol de Administración (`puestoNombre()`, ver la bitácora de niveles y puestos del mismo día); el
  Mostrador sin equipo ya no sale como supervisor.

Probado con el token de la app de cada cuenta demo (40/40): rol correcto al entrar, Mi progreso
(bajo 90 pts penalización $200; vendedor 15/5 bono; mostrador cumple), supervisor ve y no ve lo
que debe, nadie confirma lo que no le toca (con una evaluación pendiente temporal, ya borrada),
apelación guardada a nombre de la ficha con fecha y estado "apelada" (borrada), Mi semana con
cuota 216 del técnico, 0 del vendedor y del supervisor sin regla, aislamiento igual que antes.
Compatible con la 1.18 (solo agrega campos).

### App — repo TalentoEquipo, rama `feat/talento-definiciones-21oct` (sin mergear)

`8c413eb`: perfil (`PerfilContext`) desde login y `/me`; pestaña Órdenes y secciones de campo solo
para técnicos; Mi día de vendedor y mostrador con su entrada y salida (para el técnico se respeta
la decisión del 2-oct de dejarla en Talento › Asistencia); secciones nuevas Mi progreso y
Penalizaciones de mi equipo (con Confirmar); formatos compartidos (`utils/formato.js`: "10 oct al
17 oct", "-$200.00", Borrador/Cerrada, conceptos en español); Mi semana con "Cómo se compone"; el
carnet pasa a Credenciales; el supervisor ya no ve su botón de entrada encima del historial de otro.

El bundle no armaba **ni con el código de la 1.18**: `vision-camera-code-scanner` importa
`react-native-reanimated` (no instalado) en un hook que la app no usa. Se agregó en
`metro.config.js` un reemplazo mínimo (`src/shims/reanimated.js`); el escáner queda como en la 1.18.
Formatos probados 11/11; las pantallas no se pudieron ver en un teléfono desde el servidor.
