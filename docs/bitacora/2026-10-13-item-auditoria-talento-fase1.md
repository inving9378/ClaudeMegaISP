## 2026-10-13 09:30 — Auditoría completa de Talento, Fase 1/5 (solo lectura — qué funciona y qué no)

### Contexto

Pedido directo: inspección completa del módulo Talento (cálculo de salario/penalizaciones/bonos
por rol, reglas de desempeño semanal por rol, aislamiento por rol, y extensión de Mostrador a
"admin-lite"). Dado el tamaño, se acordó dividir en 5 fases y arrancar solo con la **Fase 1**:
auditoría de lo que ya existe, con Playwright real, reportando qué funciona y qué no — sin
arreglar nada todavía.

Un primer intento de delegar esta fase completa a un agente en segundo plano no ejecutó NINGÚN
paso (0 tool calls en ~340s) — se abortó y se hizo directo en esta sesión.

### Cuentas de prueba usadas (creadas y borradas en esta misma sesión)

`prueba_audit_admin` (DESARROLLADOR, sin ficha propia), `prueba_audit_tecint` (TECNICO_PLANTA),
`prueba_audit_tecext` (TECNICO_INSTALADOR), `prueba_audit_vendedor` (Vendedor + fila `sellers`),
`prueba_audit_mostrador` (Mostrador), `prueba_audit_super` + `prueba_audit_subord` (TECNICO_PLANTA
con relación supervisor_id real, para probar "Mi equipo"). Todas borradas al cierre (users,
talento_colaboradores, sellers, model_has_roles/permissions — confirmado en 0).

**Hallazgo colateral durante la creación:** al crear `prueba_audit_super`/`prueba_audit_subord`
(ids 5052/5053) ambas aparecieron con el rol `Mostrador` sin que el script lo pidiera — fila
huérfana en `model_has_roles` reutilizada por auto_increment de una cuenta de prueba borrada
anteriormente en esta misma sesión larga. Coincide exacto con el ítem ya catalogado en CLAUDE.md
("Filas huérfanas globales en model_has_permissions/role_has_permissions... Baja prioridad") —
limpiado a mano para la prueba, no se tocó el barrido de fondo (fuera de alcance de Fase 1).

### Qué se probó y qué funcionó

**Portal de Colaborador (`/talento/portal`), self-scoped, 6 roles distintos:** las 10 secciones
reales del catálogo (`Actor::sections()`) aparecen exactamente para quien deben — técnico ve "Mi
progreso" (calidad), vendedor ve "Mi progreso" (ventas) + "Mis prospectos", mostrador ve "Mi
progreso" (mostrador), un supervisor real (con subordinado vía `supervisor_id`) ve "Mi equipo" con
el nombre y métrica real de su subordinado. **Cero errores de consola, cero fallos HTTP, cero
pantallas en blanco** en ningún rol salvo el caso de abajo. Estados vacíos (Mi material sin
custodia, Mis documentos sin paquetes, reglas de desempeño "sin reglas definidas todavía") se ven
correctos y legibles, no rotos.

**Ficha de colaborador admin (`/talento/colaborador/{id}`), las 18 pestañas** (probadas sobre un
colaborador real con historial, #2): todas cargan con contenido real, cero errores inesperados.
Única nota: "Penalizaciones" dispara un 404 a `/talento/mi-ficha` cuando quien mira no tiene ficha
propia — **no es un bug**, está documentado a propósito en el propio código
(`TalentoPenalizaciones.vue:697-699`, comentario "graceful: admin/Mostrador sin colaborador
propio") desde el 28-sep; el panel igual renderiza su contenido real.

**Pantallas admin de nivel superior:** `/talento` (roster), `/talento/reglas-desempeno`,
`/talento/ventas/ranking`, `/talento/escalafon`, `/talento/mis-ventas` — las 5 responden 200, cero
errores. `/talento/reglas-desempeno` confirma **0 reglas definidas** en todo el sistema (estado ya
documentado, es justo lo que resuelve la Fase 2).

### Bug real encontrado (para arreglar en una fase posterior, NO en esta)

**"Mi equipo" del Portal queda en blanco (sin spinner, sin mensaje, sin error visible) para un
admin/DESARROLLADOR que no tiene su propia fila `talento_colaboradores`** — un caso real y
esperable (un admin de sistema no necesariamente es también un colaborador de Talento), justo el
tipo de cuenta para el que Irving pidió explícitamente "el super admin ve todo".

- **Causa raíz:** `public/talento-portal/app.js:793-802`,
  `cargarEquipoProgreso()` — la guarda `if (!colaborador.value) { ...; return; }` (línea 794)
  aborta ANTES de llamar al endpoint, así que `GET /talento/portal/mi-equipo-progreso` **nunca se
  dispara** (confirmado con traza de red: cero requests). Como `equipoProgresoCargado` nunca pasa
  a `true` ni `equipoProgreso` se llena, el template (líneas 1788-1816) no tiene una rama para ese
  estado — ni el `v-if="cargandoEquipoProgreso"` ni el `v-else-if="equipoProgreso"` aplican, y el
  `<div class="q-pa-md">` queda completamente vacío.
- El backend (`PortalTecnicoController::miEquipoProgreso`, línea 737: `$col = $actor->talento();
  if (!$col) return ...403`) tiene la MISMA restricción del lado servidor — aun si se quitara la
  guarda del frontend, el backend seguiría negando el acceso a un admin sin ficha propia con un
  403 silencioso (sin mensaje al usuario).
- **Para la fase que lo arregle:** el nav ya muestra la pestaña correctamente (gate es
  `esSuperAdminTalento()`, rol-based, correcto); falta (a) que el backend permita
  `esSuperAdminTalento()` SIN exigir `$actor->talento()`, y (b) que el frontend muestre algo (aunque
  sea "no tienes ficha propia, aquí tienes el listado completo") en vez de nada.

### Estado de la Fase 1

Cerrada. Resultado: el módulo Talento, tal como existe HOY, funciona correctamente en casi toda su
superficie — un solo bug real identificado (arriba), ningún dato roto, ninguna pantalla en blanco
inesperada salvo esa. Las fases 2-5 (reglas de desempeño reales por rol, verificación de cálculo
de salario/penalización/bono, aislamiento de datos, extensión de Mostrador a "admin-lite") siguen
pendientes de confirmación para arrancar, una por una.
