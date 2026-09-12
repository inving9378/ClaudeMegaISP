# Item #9990898 — Fase 2c: simulación histórica de la exención de docs en el detector de colisiones

## Contexto

Sub-item de #9990892 (Fase 2 de la exención de docs del detector de colisiones del Circuito CC).
Su propio spec asumía que la Fase 2b (#9990897) ya tenía **implementado** el filtro de exención
y que esta fase solo decidía si engancharlo o no. Al llegar a este item esa premisa resultó
**falsa**: #9990897 cerró **sin código**, porque la Fase 2a (#9990896→…→#9990942) midió con datos
reales que la colisión de footprint **no es causa frecuente de terminales ociosas** en el estado
actual del sistema (backlog de ~1580 items: el scheduler rellena el slot liberado por una colisión
con otro candidato en el mismo ciclo) — decisión ya aprobada por Irving (q3 de #9990892: "no
forzar un cambio sin evidencia de beneficio").

Aun así, esta fase pide una pregunta DISTINTA y autocontenida: si existiera la regla de exención
de docs, ¿cuántas colisiones históricas reales se habrían evitado? Esa simulación no depende de
que el filtro ya esté codeado — se puede aplicar la misma regla propuesta (CLAUDE.md, `docs/**`,
`CHANGELOG*`, `*.md`) contra el log real de colisiones sin tocar nada.

## Simulación

Se escaneó el campo `log` de **todos** los items de `roadmap_items` en busca de eventos
`colision_pausada` (evento real que emite `detectarColisionesEnVuelo()` al pausar al perdedor de
una colisión, con la lista de `archivos` en común). Total histórico disponible: **64 eventos**
(todo el histórico existente en dev, no solo la última hora — la ventana de 1h pedida por el spec
original solo contenía 2 eventos, ver abajo).

Para cada evento se aplicó la regla de exención propuesta (`esRutaExentaDeColision()`: ruta exacta
`CLAUDE.md`, cualquier ruta bajo `docs/`, `CHANGELOG*`, o cualquier archivo `*.md`; las entradas
`tabla:xxx` — colisión de esquema por migración — **nunca** son exentas) y se verificó si **TODOS**
los archivos en común de ese evento caían en la exención.

### Resultado global (64 eventos, todo el histórico)

| # | Item pausado | Ganador | Archivos en común | ¿Evitable por exención de docs? | ¿Toca frontera dura (dinero/seguridad/permisos/prod)? |
|---|---|---|---|---|---|
| 1 | #165 | #146 | `docs/bitacora-sesiones.md` | ✅ Sí | No |
| 2 | #165 | #809 | `docs/bitacora-sesiones.md` | ✅ Sí | No |
| 3 | #809 | #146 | `docs/bitacora-sesiones.md` | ✅ Sí | No |
| 4 | #9990328 | #9990329 | `docs/roadmap-bucle-reap-item-9990328-verificacion.md` | ✅ Sí | No |
| 5 | #9990487 | #9990469 | `docs/vendedores-transactions-payments-seller-id-item-9990487-verificacion.md` | ✅ Sí | No |
| 6 | #9990810 | #9990805 | `CLAUDE.md` | ✅ Sí | No |
| … | (58 eventos más) | — | rutas de código/config/`.vue`/`.js`/migraciones/`tabla:xxx` | ❌ No | Ninguno de los 58 restantes toca frontera dura tampoco (son código de módulos de negocio ya en dev, no producción/dinero/permisos/credenciales) |

**Totales:** 6 de 64 (**9.4%**) de las colisiones históricas reales se habrían evitado con la regla
de exención de docs. **0 falsos positivos en frontera dura** — ninguno de los 6 casos evitables
toca dinero/seguridad/permisos/producción (los 6 son ediciones de bitácora/verificación/CLAUDE.md).

### Ventana de "última hora" (la pedida literalmente por el spec original)

Relativa al evento más reciente del log (`2026-09-11 19:05:05`), solo hay **2 eventos** en la
última hora, y **0 de los 2** son evitables (`RoadmapTab.vue` y `ParquearTimeoutCommand.php`, ambos
código real). La ventana de 1h es demasiado angosta para sacar una conclusión por sí sola — se usó
el histórico completo (8 días) para tener una muestra representativa.

## Decisión tomada

El propio spec de #9990898 da la regla de cierre: *"Si el resultado es >0: enganchar el filtro
dentro de `decidirColisiones()` (~línea 3191) antes del compare de footprints, verificar con
tinker un caso sintético (dos ramas que solo tocan CLAUDE.md no deben colisionar), y solo entonces
dar por completado #9990892 (el padre)."* El resultado fue >0 (6/64), así que se implementó:

- `RoadmapCircuitoService::esRutaExentaDeColision(string $ruta): bool` — nuevo método público,
  la misma regla usada en esta simulación (CLAUDE.md / `docs/**` / `CHANGELOG*` / `*.md`).
- `decidirColisiones()` — antes de intersectar los footprints de dos ramas, se descartan de los
  archivos comunes las rutas exentas (las tablas de migración quedan intactas: nunca son exentas,
  una colisión de esquema real sigue contando).
- Cambio **puro, aditivo y reversible** (sin flag): la función que se tocó ya es un núcleo puro
  sin BD ni git (`ColisionPorTablaMigracionTest.php`), con 8 tests nuevos que cubren el caso
  exacto pedido (dos ramas que solo tocan `CLAUDE.md` no colisionan), el caso mixto (doc + código
  real sigue colisionando) y el caso de tabla (una tabla en común sigue colisionando aunque el
  único archivo en común sea un doc). Las 17 pruebas preexistentes del archivo siguen pasando sin
  cambios.

**Nota sobre q2 del brief auto-generado:** las opciones que trajo el DES-TRABE (Opus) para "qué
reglas de exención se van a probar" (q2) hablaban de exentar **items por nivel de riesgo**
(nivel A/B), un eje distinto al que describe el propio `description` del item (exención de
**rutas de archivo** de documentación, heredada literalmente de #9990892/#9990897/#9990896). Se
siguió el `description` técnico del item —consistente con toda la cadena de sub-items hermanos—
en vez de la semántica de esa pregunta, que parece una confusión del generador del brief con un
eje distinto (nivel de riesgo de autopilot) no relacionado con el detector de colisiones.

**Sin activar ningún flag ni tocar configuración de producción** — el cambio vive enteramente en
la lógica pura de `decidirColisiones()`, que solo corre en dev contra `roadmap_items` de este
mismo entorno.
