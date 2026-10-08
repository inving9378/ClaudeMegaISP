## 2026-10-08 — Reglas de desempeño semanal: verificación en navegador, 2 bugs reales encontrados y corregidos

Irving pidió verificar que lo construido ayer (reglas de comisiones/penalizaciones semanales por
combinación de roles) estuviera completo y se mostrara bien — no solo probado por `tinker` como se
dejó documentado ayer, sino de verdad en un navegador.

### Cómo se probó

El servidor no tiene Chrome instalado (la herramienta de navegador configurada apunta a un canal
`chrome` que no existe aquí, y `npx playwright install chrome` requiere `sudo`, sin acceso). Se usó
en su lugar el Chromium que `npx playwright install` ya había descargado antes en este equipo
(`~/.cache/ms-playwright/chromium-1243/`), manejado directo con la librería `playwright` (ya
presente como dependencia del proyecto) desde un script propio — mismo navegador real, con clics y
tecleo reales, solo sin pasar por la herramienta MCP.

Se creó un usuario de administrador temporal (`pruebaVerifReglasUI`, rol DESARROLLADOR) solo para
esta sesión de pruebas — nunca se tocó ninguna cuenta real. Borrado al final junto con toda regla,
evaluación, penalización y ficha de colaborador de prueba creada durante la verificación — confirmado
por conteo que no quedó ningún rastro.

### Bug 1 — guardar una regla nueva (sin duplicado) tronaba

Síntoma real visto en pantalla: al dar clic en "Guardar" sobre una regla nueva, aparecía el error
`Attempt to read property "name" on null` dentro del propio modal, en vez de guardar.

Causa: `abort_if($dup, 422, '...' . $dup->name . '...')` en
`TalentoReglaDesempenoController::validateRule()` — PHP evalúa **todos** los argumentos de una
función antes de llamarla, así que el mensaje (con `$dup->name`) se arma siempre, incluso cuando
`$dup` es `null` (el caso normal: no hay ninguna regla duplicada). El caso "si hay duplicado" nunca
se había probado con el camino feliz (sin duplicado) en la misma sesión de pruebas — por eso no se
detectó ayer con las pruebas por `tinker`, que sí cubrían el caso duplicado pero no en la misma
secuencia que dispara el bug.

Corregido: se cambió por un `if ($dup) { abort(...) }` real, que solo arma el mensaje cuando de
verdad hay un duplicado.

### Bug 2 — el selector de "otros roles" se veía pero no se podía clickear

Al marcar "Tiene más roles" y abrir el desplegable, las opciones se mostraban en pantalla pero un
clic normal no hacía nada — Playwright lo confirmó con el mismo síntoma que vería un usuario real
("element intercepts pointer events"). Medido con precisión: el menú de Quasar (la librería del
desplegable) siempre usa `z-index:6000` para pintarse por encima de todo; el modal de esta pantalla
—copiado del patrón ya usado en Penalizaciones— usaba `z-index:9999`, mayor, así que el modal mismo
quedaba pintado por encima de su propio desplegable. El patrón de Penalizaciones nunca había tenido
este problema porque esa pantalla no usa ningún desplegable de Quasar dentro de su modal — esta es
la primera vez que se combinan ambos, y la combinación tenía este choque.

Corregido: bajado el `z-index` del modal a `2000` (sigue por encima del contenido normal de la
página, por debajo del menú de Quasar). Verificado con un clic **normal**, sin forzar nada.

### Hallazgo menor — 'Super Administrador' no estaba excluido del selector

Al revisar el catálogo de roles mostrado, apareció seleccionable "Super Administrador" (el
duplicado en español del rol `super-administrator`, ya documentado en `CLAUDE.md` como deuda
pendiente de Fase 4) — la lista de "roles de sistema a excluir" solo tenía la forma en inglés/kebab.
Agregado el duplicado a la misma constante, en el componente Vue y en el servicio de evaluación.

### Verificado de punta a punta, con los 2 fixes aplicados

- Crear una regla real con combinación **Mostrador + Vendedor** (clic normal, sin forzar nada) →
  aparece correcta en la tabla: los 2 roles como chips, piso de prospectos, techo de ventas y monto
  del bono, todo visible y legible.
- Confirmado que "Mostrador" (el rol base ya elegido) nunca aparece en el desplegable de "otros
  roles" — exactamente lo pedido originalmente.
- Flujo completo de "Pendientes de confirmar": se simuló una penalización sugerida real, se confirmó
  con el botón desde la pantalla (con el aviso correcto de "esto descuenta dinero real"), y se
  verificó directo en la base de datos que el descuento se aplicó de verdad, con el monto correcto y
  atribuido al supervisor correcto — mismo camino de dinero que ya usa el sistema de Penalizaciones,
  sin atajos.
- De paso, se confirmó que el candado de "necesitas tener tu propia ficha de colaborador para
  confirmar una penalización" funciona correctamente (se topó con él primero, con el usuario de
  prueba sin ficha, antes de dársela a propósito para completar la prueba).

### Estado final

Commits `ed795963` (fix) + `e9a5c85e` (merge a `main`). La pantalla queda verificada de verdad en
navegador, no solo por código — lista para que Irving cargue las reglas reales.
