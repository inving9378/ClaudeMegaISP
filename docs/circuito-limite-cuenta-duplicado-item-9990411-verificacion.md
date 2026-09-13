# Item #9990411 — FASE 1+2 "detectar causa=limite_cuenta" — RESUELTO (duplicado ya mergeado por #9990426 + #9990416)

## Qué pedía el item

Sub-item de seguimiento de #9990410. Pedía, en dos archivos:

1. `deploy/circuito/vuelta.sh` — antes de la rama `default` de detección de causa de corte,
   agregar: si el log contiene el fragmento estable `'session limit'` → `CAUSA="limite_cuenta"`,
   con extracción best-effort (nunca bloqueante) de la hora de reset (`resets HH(am|pm)`) para
   pasarla como `--hora-reset=` a `circuito:parquear-timeout`.
2. `app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php` — aceptar `--causa=limite_cuenta`
   y `--hora-reset=`, con un TERCER camino (paralelo a "reanuda si avanzó" / "escala a la bandeja")
   que regresa el item a `estadoAprobadoPrevio()` **intacto**: sin incrementar `veces_timeouteo` ni
   `reanudaciones_timeout` (la señal que consume `JarvisService::caberEnVuelta()`), sin tocar
   `escalaciones_fingerprint`, soltando el claim igual que las demás causas, y dejando en el log un
   evento `limite_cuenta_detectado` con el motivo en texto llano.

## Qué se encontró al ejecutarlo

La rama del item (`circuito/item-9990411-fase-12-detectar-causalimite-cuenta-e`, commit `8d8da4ce`)
ya estaba escrita y "cerrada" desde el 2026-09-06 (comentario de cierre de esa sesión: verificado con
`php -l`, `bash -n`, grep contra el texto exacto y una prueba real con transacción+rollback). El
`merge-runner` intentó mergearla dos veces (2026-09-06 17:12 y 2026-09-13 08:53) y **ambas escaló por
conflicto** — en `deploy/circuito/vuelta.sh` la primera vez, en `ParquearTimeoutCommand.php` la
segunda. Irving forzó un "desparque-destrabe" el 2026-09-13 10:31 pidiendo que una terminal
resolviera el conflicto.

Investigando el conflicto real (`git diff main <rama>` en ambos archivos) se confirma que **la causa
NO es un conflicto de edición simultánea sobre líneas vecinas: es trabajo íntegramente DUPLICADO**.
Mientras la rama de #9990411 estuvo parada esperando merge, otros dos items ya resolvieron exactamente
lo mismo y sí llegaron a `main`:

- **#9990426** — "detecta causa=limite_cuenta en vuelta.sh" (commit `ad3a8d16`, ya en `main`).
- **#9990416** — "FASE 2: causa=limite_cuenta en ParquearTimeoutCommand sin castigar el item"
  (commit `593e7e9f`, ya en `main`).

Es decir: #9990410 se descompuso (por alguna vía) en un split limpio de 2 fases (#9990426 vuelta.sh +
#9990416 ParquearTimeoutCommand) que sí se mergeó, **y en paralelo** también se creó/trabajó #9990411
como el mismo trabajo combinado en un solo item — clásica carrera de timing entre descomposiciones
independientes del mismo item padre (mismo patrón ya documentado varias veces en `CLAUDE.md` para
otros items: #733/#741/#753/#9990003/#9990353/#9990624-Fase1/#9990658/#9990869).

Peor: mientras la rama de #9990411 seguía sin mergear, `main` siguió avanzando sobre el mismo código
con **guards adicionales que la rama de #9990411 NO tiene**:

- **#9990855** agregó a `ParquearTimeoutCommand.php` la "GUARDA DE ESTADO" (no pisar una decisión ya
  tomada en la misma vuelta) y la "GUARDA DE PARAGUAS" (nunca escalar por timeout a un item con
  sub-items abiertos) — ambas se pierden si se mergea la rama vieja tal cual (el diff las muestra
  como líneas removidas).
- **#9990901** agregó a `vuelta.sh` el bloque que inyecta la respuesta de Irving con precedencia en
  el prompt del ejecutor (`circuito:respuesta-prompt`) — también se pierde con la rama vieja.

Mergear la rama de #9990411 tal cual **regresionaría** ambas piezas. Ese es el conflicto real: no es
que falte resolver un `<<<<<<<`, es que la rama entera está superada por trabajo posterior.

## Verificación de que `main` ya cumple el DoD completo del item

Contra el `main` actual (`app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php` +
`deploy/circuito/vuelta.sh`):

- `vuelta.sh` línea 332: `elif grep -aq 'session limit' "$LOG"; then` → `CAUSA="limite_cuenta"`,
  con extracción best-effort de la hora (`grep -aoiE 'resets [0-9]{1,2}(am|pm)'`) y flag opcional
  `--hora-reset=` solo si se obtuvo (`HORA_RESET_FLAG=""` si no hay match) — nunca bloquea la
  detección de la causa. ✅ igual a lo pedido.
- `ParquearTimeoutCommand.php`: `--causa` acepta `limite_cuenta`, `--hora-reset` existe, y el tercer
  camino (`if ($causa === 'limite_cuenta')`) hace `$destino = $circuito->estadoAprobadoPrevio($item)`,
  solo toca `estado_aprobacion`/`aprobado_por`/`worker_sid`/`claimed_at`/`log` — **no** incrementa
  `veces_timeouteo` ni `reanudaciones_timeout`, **no** toca `escalaciones_fingerprint` — y registra
  `'evento' => 'limite_cuenta_detectado'` con `motivo` y `hora_reset`. ✅ igual a lo pedido.

Ambos archivos, además, ya traen las guardas más nuevas (#9990855) intactas.

## Decisión (registrada, `circuito:reportar --tipo=decision`)

**No mergear** la rama `circuito/item-9990411-fase-12-detectar-causalimite-cuenta-e` — haría un
downgrade de `main`. Cerrar el item documentando que su objetivo ya está cumplido por #9990426 +
#9990416, con `sin_merge_esperado=true` (el trabajo de esta rama es superfluo: el resultado ya existe
en `main` por otra vía) y su motivo.

## Resultado

`estado_aprobacion = completado`. **Sin cambio de código** — el comportamiento pedido ya está en
`main` desde antes de que se reclamara este item. La rama vieja de #9990411 se deja sin mergear a
propósito.
