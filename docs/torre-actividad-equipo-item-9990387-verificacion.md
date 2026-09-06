# Item #9990387 — Frontend "Actividad del equipo" en la Torre (RESUELTO — ya implementado)

## Premisa del item

El item (sub-item de seguimiento de #9990375) pedía crear el frontend de la pestaña "Actividad
del equipo" de la Torre de Control: componente Vue `TorreActividadEquipo.vue` + wiring en
`ReleasesIndex.vue` (import, `components`, `TABS_VALIDAS`, `<li>` de navegación gateado por
`torre.actividad.view`, y el `<torre-actividad-equipo v-if="tab === '...'">`), asumiendo que
"sin este sub-item, la pestaña... no es visible en ningún lado".

## Hallazgo

El trabajo **ya está hecho y mergeado en `main`**, commit `616844f9` ("Torre: pestaña Actividad
del equipo (frontend) — #9990375", autor del commit el mismo usuario git del repo, 2026-09-05
17:02:02), ancestro confirmado de `HEAD` (`git merge-base --is-ancestor 616844f9 HEAD` → sí).
No es un caso de "falta hacerlo": es la misma familia de carrera de timing entre el ciclo del
circuito y la generación de sub-items de seguimiento que ya se documentó varias veces en
`CLAUDE.md` (#733/#741/#753/#9990003/#9990353) — este sub-item se generó a partir del spec
original de #9990375 sin ver que, para cuando llegó a ejecutarse, otra vuelta ya lo había resuelto.

## Verificación punto por punto (contra el spec del item)

1. **`resources/js/components/module/releases/torre-control/TorreActividadEquipo.vue`** — existe
   (311 líneas), sigue el patrón de `TorreHistorialAcciones.vue`: `fetch` on mount vía axios a
   `/api/roadmap/torre/actividad-equipo`, `darkMode` de `../../../../hook/appConfig.js` con
   tokens `--ae-*` + variante `.ae-dark` (mismo patrón que `--ha-*`), selector de rango
   (`fecha_inicio`/`fecha_fin` + botón "Filtrar" + atajos "Hoy"/"7 días"/"30 días", default
   últimos 7 días).
2. **Separación medido/inferido** — dos `<section>` visualmente distintas con tag "dato duro" vs.
   "no es certeza", cada una con su propio `aviso` tomado tal cual de la respuesta del backend
   (no se inventó texto). "Medido": tabla por terminal (`worker_sid`/reclamados/completados/tiempo
   formateado con `fmtDuracion`), vueltas del circuito (duración/modo/resumen), commits por autor
   + detalle expandible de recientes. "Inferido": `por_quien_en_log` y `por_usuario_ui`, con su
   propio aviso de baja certeza, sin mezclar con lo medido.
3. **Wiring en `ReleasesIndex.vue`** — las 4 piezas del spec están aplicadas:
   - Import línea 323 + `components: {..., TorreActividadEquipo, ...}` línea 334.
   - `TABS_VALIDAS` incluye `'actividad-equipo'` (línea 344).
   - `<li class="nav-item" v-if="hasPermission.data.canView('torre.actividad.view')">` (línea 60).
   - `<torre-actividad-equipo v-if="tab === 'actividad-equipo' && hasPermission.data.canView('torre.actividad.view')" />` (línea 130) — doble guard (tab + permiso), más estricto que lo pedido.
4. **Build** — `bash deploy/circuito/npm-build.sh` (semáforo del circuito) compiló limpio:
   "Compiled Successfully" + "Mix: Compiled successfully in 55.40s", sin errores (solo warnings
   preexistentes de Sass/browserslist, no relacionados).
5. **Backend real, no solo curl/tinker de la descripción original** — reverificado en esta vuelta:
   - Migración `2026_09_05_230000_permiso_torre_actividad` corrida (`Ran`, batch 621).
   - Permiso `torre.actividad.view` (id=810) existe y está asignado a `super-administrator` +
     `DESARROLLADOR`.
   - `RoadmapController::actividadEquipo()` invocado directo (auth simulada como usuario
     DESARROLLADOR) devuelve `ok:true`, `rango` con los últimos 7 días por default, 6
     terminales medidos, 732 commits, y el aviso de "inferido" explícito sobre el login
     compartido (Irving/David) — end-to-end funcional, no solo código estático.

## Conclusión

Nada pendiente de este sub-item. La pestaña "Actividad del equipo" ya es visible en `/releases`
(pestaña "Actividad del equipo" del listado de tabs de la Torre) para roles con
`torre.actividad.view`, y el fetch real trae datos reales. **Sin cambio de código** — este commit
solo documenta la verificación.
