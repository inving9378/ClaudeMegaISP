# Portal Cliente — evaluación migración a bcrypt (Opción B) — item #154

**Alcance de este documento:** el item #154 pide *evaluar* (no ejecutar) la migración de
`client_main_information.password` de texto plano a bcrypt. Esto es esa evaluación. La
migración en sí (Opción B) queda **fuera de alcance de esta vuelta** — es una decisión de
diseño que toca autenticación real de clientes en producción (frontera dura de
credenciales/seguridad) y requiere decisión explícita de Irving antes de tocar código. Se
deja registrada como sub-item (#154 permanece paraguas; ver sub-item creado).

## 1. Estado actual (verificado en código y BD, 2026-08-26)

- Login del portal (`AuthController::login`) valida contra
  `client_main_information.password` (la "Contraseña WEB" editable desde la ficha del
  admin) en **texto plano**, comparado con `hash_equals()`.
- Auto-registro (`registro()`) y recuperación (`recuperar()`) **escriben** la nueva
  contraseña en la misma columna, también en texto plano.
- `portal_password` (columna bcrypt, remanente de un intento anterior) está en desuso
  para login — solo 4 filas la tienen poblada (auto-registros históricos previos a la
  Fase 2, cuando el login sí usaba esa columna).
- Columnas: `password` y `portal_password` son ambas `varchar(255)` — hay espacio de
  sobra para un hash bcrypt (60 caracteres).
- Filas con `password` poblado en texto plano hoy: **4,699**.

## 2. Por qué el sistema NO está ya en bcrypt (contexto histórico)

`portal_password` (bcrypt) fue el mecanismo original del guard `cliente`. Se abandonó en
Fase 2 porque la ficha de administración (`InformationClientCrud.vue`, sección
`credenciales`) muestra y permite **editar directamente** el campo `password` como texto
plano: es la misma "Contraseña WEB" que ve/escribe un admin en el formulario estándar
data-driven del sistema (`Module::getfields()` sobre `client_main_information`), sin
mutator ni tratamiento especial. Unificar el login contra ese campo (en vez de mantener
dos contraseñas separadas — una que edita el admin y otra bcrypt que usa el login) resolvió
una inconsistencia real: antes, un admin cambiaba la "Contraseña WEB" en la ficha y el
cliente no podía entrar al portal con ella (el login comparaba contra la columna bcrypt
distinta). La Fase 2 sacrificó bcrypt para arreglar esa desconexión.

**Esta es la tensión central que cualquier migración a bcrypt tiene que resolver, no un
detalle secundario.**

## 3. Qué exige realmente la Opción B (hashear `password`)

1. **Mutator en el modelo** (`PortalClient` / `ClientMainInformation`): al asignar
   `password`, hashear automáticamente (`Hash::make`) antes de guardar. Cubre los 3
   escritores actuales (login no escribe, pero `registro()` y `recuperar()` sí) más
   cualquier futuro que asigne el campo.
2. **Comparación**: `AuthController::login` cambia de `hash_equals(plano, plano)` a
   `Hash::check($input, $cmi->password)`.
3. **Backfill de datos**: las 4,699 filas con contraseña en claro deben re-guardarse como
   hash. Es un `UPDATE` masivo, ejecutable una sola vez vía migración/comando — mecánico,
   pero **irreversible**: una vez hasheado, el valor en claro se pierde para siempre (no
   hay `down()` que regrese el texto original).
4. **La ficha de admin dejaría de poder mostrar la contraseña actual.** Hoy el campo
   `password` en `credenciales` es un input de texto plano que un admin puede leer y
   editar sin fricción (útil en soporte telefónico: "¿cuál es mi contraseña?" → el admin
   la ve y se la dicta). Con bcrypt, ese input mostraría el hash (ilegible) o tendría que
   ocultarse/convertirse en "establecer nueva contraseña" (solo escritura, nunca lectura)
   — esto es un **cambio de UX/flujo de soporte**, no solo un cambio de columna. Requiere
   decisión de Irving sobre cómo debe operar soporte de ahí en adelante.
5. **Normalizar el padding de `user`** (deuda relacionada, mencionada en el mismo punto de
   la Hoja de Ruta): hoy el login tolera `"004981"` vs `"4981"` con `ltrim` en tiempo de
   query (parche en `AuthController::login`). Es independiente de bcrypt — se puede
   resolver por separado (normalizar en escritura, no en cada lectura) sin que dependa de
   esta migración.

## 4. Riesgo de la migración vs. riesgo del estado actual

- **Riesgo de NO migrar (estado actual):** cualquiera con acceso de lectura a la BD
  (dump, backup, replica, admin malicioso) ve contraseñas de clientes en claro. Mitigado
  hoy solo por controles de acceso a la BD/backups, no por el diseño del dato.
- **Riesgo de migrar mal:** un bug en el mutator o en el backfill deja a **todos los
  clientes sin poder entrar al portal** (autenticación real, cara al cliente, ya
  "Operativo" según CLAUDE.md) hasta corregirlo. Es exactamente el tipo de cambio que
  esta arquitectura de circuito trata como frontera dura (credenciales/seguridad +
  irreversible sobre datos reales).

## 5. Recomendación

Proceder con la Opción B, pero **no como cambio mecánico de una sola vuelta**: es nivel de
diseño (toca UX de soporte + auth real + backfill irreversible). Se registra como sub-item
separado para que Irving decida explícitamente, con esta evaluación como spec de entrada,
sin que quede otra vez ambiguo qué falta para decidir. Camino recomendado cuando se
apruebe:

1. Mutator + `Hash::check` + backfill en una migración con **dry-run** previo (patrón ya
   usado en el pipeline de deploy: `deploy:dry-run-migrations`).
2. Decidir en paralelo qué ve el admin en `credenciales` → `password`: la opción más
   simple y reversible es convertir ese input en "establecer nueva contraseña" (siempre
   vacío al cargar, solo escribe si se llena), sin tocar el resto del formulario
   data-driven.
3. Ejecutar backfill en una ventana controlada (dev primero, con conteo de filas antes/
   después para verificar 1:1).

## 6. Conclusión de este item (#154)

Evaluación completa. La migración real queda como sub-item pendiente de decisión de
Irving (nivel C — diseño exclusivo suyo, toca auth real + UX de soporte + dato
irreversible). #154 se cierra como la evaluación solicitada por el título del item.
