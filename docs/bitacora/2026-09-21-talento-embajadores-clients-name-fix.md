## 2026-09-21 — Fix: `TalentoEmbajadoresController::embajadorData()` consultaba columnas inexistentes

**Contexto:** hallazgo encontrado durante la verificación en navegador del lote 5
de la Fase B (tema Torre en Talento), documentado ahí sin tocar código. Esta
entrada cierra ese hallazgo con el fix aplicado, a pedido explícito de Irving.

**Bug:** en la pantalla "Talento → Colaboradores con Roles Múltiples"
(`/talento/embajadores-colabs`), las columnas "Es Embajador", "Referidos" y
"Comisiones acumuladas" siempre mostraban "—" para todos los colaboradores.
Causa: `embajadorData()` hacía `DB::table('clients')->where('email',
...)->first(['id','name','email'])`, pero la tabla `clients` **no tiene columnas
`name` ni `email`** — esos datos viven en `client_main_information` (columna
`client_id` como FK a `clients.id`). Cada llamada (una por fila de la tabla, 20
en la pantalla de prueba) fallaba con `SQLSTATE[42S22]: Column not found: 1054`,
atrapado en el frontend por `.catch(() => null)` — la pantalla no se rompía,
solo perdía ese dato en silencio.

**Segundo hallazgo del mismo origen (misma causa, más abajo en el método):**
`->with('referredClient:id,name,email')` tenía el mismo problema — `Client`
tampoco tiene columnas `name`/`email` propias (tiene un accessor `client_name`
vía `$appends`, que lee de `client_main_information` internamente, pero no es
lo que el frontend esperaba). Este segundo bug solo se disparaba con un
embajador que SÍ tuviera referidos (`$referralCount > 0`) — no había ningún
caso así en los datos de prueba de dev, así que nunca se vio en pantalla, pero
habría fallado igual en cuanto existiera un embajador real con referidos.

**Fix** (`app/Modules/Addons/Talento/Controllers/TalentoEmbajadoresController.php`):
- Resolución del cliente reapuntada a `ClientMainInformation::where('email',
  ...)->first(['client_id','name'])` — mismo patrón ya establecido en
  `EmbajadorExtApiController::arbol()`/`recompensas()` (Embajadores).
- El eager-load de referidos reapuntado a
  `referredClient.client_main_information:client_id,name` (mismo patrón), y la
  colección se transforma explícitamente a la forma plana que el frontend ya
  esperaba (`recent_referrals[].referred_client.{id,name}`) — así no hace falta
  tocar el Vue, y de paso el JSON deja de arrastrar el modelo `Client` completo
  con sus ~5 relaciones anidadas (payload mucho más chico).

**Verificado:**
- `php -l` limpio.
- Tinker autenticado como `david_marsal`: los 6 colaboradores que antes
  daban 500 ahora responden bien (`is_ambassador:false` con `client_id`/
  `client_name` resueltos, o el mensaje "no registrado" cuando corresponde).
- Simulación directa de la rama con datos (`embajador_id` real con 4 referidos)
  → los 4 nombres (`JENIFER ASTRID`, `ELOY FLORENCIO`, `ERNESTO`, `JOVANI`) se
  resuelven correctamente en `referred_client.name`.
- Playwright real (`david_marsal`) contra `/talento/embajadores-colabs`: cero
  errores 500 (antes 20), solo quedan los 404 del logo faltante preexistente
  (sin relación). Captura confirma la columna "Es Embajador" ya resuelve "No"
  real en vez del "—" de antes.

Rama `fix/talento-embajadores-clients-name-column`, mergeada a `main`.
