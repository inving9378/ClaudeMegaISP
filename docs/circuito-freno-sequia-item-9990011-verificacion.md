# Item #9990011 — Freno de sequía N2 Fase 3a-ii (RESUELTO — ya implementado en el commit de #925)

## Premisa del item

#9990011 nació como sub-item de seguimiento de #925 y pedía, como "Fase 3a-ii":

1. Hacer `evaluarApagarGasto()` idempotente (quitar el corte `|| $this->gastoApagadoDesde() !== null`
   del guard, para que el `updateOrInsert` renueve el timestamp aunque ya estuviera apagado).
2. Agregar (opcional) `estadoGastoUi(): array` de solo lectura (`armado`/`disparado`/`medio_abierto`
   + countdown) para que una futura UI (Fase 3b / #926) lea el estado sin duplicar lógica.
3. Verificación end-to-end (a-f) de todo el freno de sequía N2 con half-open.

El item asumía que esto podía NO estar hecho todavía si la "Fase 3a-i" (sub-item hermano) seguía
sin commitear, e instruía revisar `git log`/grep de `reintentoActivo` en `AuditorService.php` antes
de empezar.

## Hallazgo

Ambas piezas — la "3a-i" (config keys + `reintentoActivo()`/`venceReintento()` + rama half-open en
`gastoApagado()`) y la "3a-ii" pedida aquí (idempotencia de `evaluarApagarGasto()` +
`estadoGastoUi()`) — se hicieron **en un solo commit**, ya en `main` desde antes de que este
sub-item se creara:

- `da5cb758` — *feat(circuito#925): half-open del freno de sequia N2 en AuditorService*
  (2026-09-03 16:52:03), precedido por `e9f36453` (defaults de fábrica
  `gasto_reintento_min`/`gasto_reintento_activo` en `config/circuito.php`).

El diff de `da5cb758` muestra exactamente el cambio pedido en el guard de `evaluarApagarGasto()`:

```diff
-        if ($racha < $umbral || $this->gastoApagadoDesde() !== null) {
+        if ($racha < $umbral) {
             return;
         }
```

y agrega `estadoGastoUi()` con la firma y semántica (`armado`/`disparado`/`medio_abierto` +
`reintento_en_segundos`) descritas literalmente en el spec de #9990011. El propio mensaje de commit
de `da5cb758` ya documenta una verificación a-f equivalente a la pedida aquí, incluyendo la misma
advertencia operativa: *"el swarm de dev completa items constantemente y contaminaba pruebas en
vivo"*.

Los commits posteriores que tocaron `AuditorService.php` (`9fce8cff`, `4d877830`, `c543e857`,
`c1ee65f1`/`1e83e7cc`, `d0d8e999`, `9c35aa41`, `deb76d40`, `4db3d298`) son detectores/perillas no
relacionados (null-safety, env_runtime, candado de esquema, `slots_libres`, `BarridoService`); no
tocaron `gastoApagado()`, `evaluarApagarGasto()`, `estadoGastoUi()`, `reintentoActivo()` ni
`venceReintento()`.

## Re-verificación independiente (2026-09-04, este item)

Se repitió la verificación a-f contra el código actual de `main`, con el mismo cuidado de aislar el
chequeo de "item real completado" (confirmado contaminante: en la ventana de prueba había 5 items
reales completados en las últimas 2h por el swarm de dev) usando reflection sobre los métodos
privados en vez de pasar por `gastoApagado()` completo cuando la vía vieja podía interferir:

- **(a) TTL vencido dispara el sondeo** — con `gasto_reintento_min=30` (default) y un
  `circuito_auditor_gasto_apagado_desde` de hace 45 min: `reintentoActivo()=true`,
  `venceReintento($desde)=true` → la rama half-open dejaría pasar el sondeo.
  `estadoGastoUi()` devuelve `{"estado":"medio_abierto","reintento_en_segundos":0}`. ✅
- **(b) El freno sigue frenando tras el sondeo** — invocando `evaluarApagarGasto($umbral)` por
  reflection con el timestamp viejo ya puesto: el `updateOrInsert` **renueva** el timestamp
  (`before != after`, `after` ≈ `now()`); con el timestamp renovado, `venceReintento()` vuelve a
  `false` → el freno NO quedó anulado, hay que esperar otra ventana completa. ✅
- **(c) Toggle OFF preserva el comportamiento anterior** — con
  `gasto_reintento_activo=false` (vía `config()`) y el mismo timestamp viejo:
  `reintentoActivo()=false` → el half-open nunca deja pasar el sondeo, independientemente de
  `venceReintento()`. Comportamiento idéntico al de antes de #925. ✅
- **(d) La vía vieja sigue intacta** — `huboItemRealCompletadoDesde()` y `rearmarGasto()` existen
  sin cambios desde antes de `da5cb758` (confirmado por el diff: 0 líneas tocadas en esos métodos)
  y siguen siendo el primer chequeo dentro de `gastoApagado()`, antes de la rama half-open. ✅
- **(e) Kill switch y `auditor_activo` siguen ganándole a todo** — `debeCorrer()` evalúa
  `habilitado()` (auditor_activo) y `$this->circuito->isPaused()` (kill switch) **antes** de
  cualquier lógica de `gasto_apagado`/racha; el orden no fue tocado por `da5cb758` ni por ningún
  commit posterior. ✅
- **(f) Sintaxis y arranque** — `php -l` limpio en `AuditorService.php` y `config/circuito.php`;
  `php artisan --version` bootea (`Laravel Framework 10.48.4`). ✅
- **(g) Limpieza** — la fila de prueba `circuito_auditor_gasto_apagado_desde` en `settings` se
  eliminó al terminar (no existía antes de la prueba, así que el estado final es el mismo: fila
  ausente = freno armado). ✅

## Conclusión

No hay nada que implementar: el spec completo de #9990011 (idempotencia + `estadoGastoUi()` +
verificación a-f) ya estaba en `main` desde `da5cb758`, hecho como parte del mismo trabajo de #925
que este sub-item asumía incompleto. Cerrado sin cambio de código de aplicación — solo este
documento de verificación.
