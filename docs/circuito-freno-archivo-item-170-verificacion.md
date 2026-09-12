# Item #170 — Circuito: freno de mano fuera de la BD (RESUELTO — ya implementado directamente por Irving)

## Contexto

El item pedía: `isPaused()` leía la bandera de pausa desde la tabla `settings`
(`RoadmapCircuitoService.php:133`). Con la BD vacía la comprobación lanzaba excepción y el
circuito no podía auto-pausarse: el freno dependía justo de lo que se rompe. El 22-ago eso dejó
a `vuelta.sh` 1 día 21 horas en bucle con 43,947 invocaciones de `claude -p`. Se pedía mover el
centinela a un archivo en disco y hacer `isPaused()` fail-closed: si no puede determinar el
estado, pausar.

El revisor (#338) autorizó el item el 2026-08-25 16:50:01 (aprobado_revisor, confianza alta).

## Hallazgo

Al llegar a ejecutar el item, el fix **ya estaba implementado y mergeado a `main`** desde el
mismo día de la aprobación: commit `b426bc42` ("feat(circuito#170): el freno de mano sale de la
base — centinela en archivo, fail-closed"), autor `Irving MegaISP`, timestamp
`2026-08-25 16:57:10` — 7 minutos después de la autorización del revisor. Fue un commit directo
a `main` (no pasó por `circuito:rama`/`circuito:integrar`), por eso el item nunca se marcó
`completado`: el trabajo se hizo, pero el bookkeeping del roadmap se quedó atrás. Mismo patrón de
"gap de cierre" ya documentado varias veces en `CLAUDE.md` (p. ej. #9990003, #9990353, #9990658).

Endurecido después por `d40eb231` ("FASE 4a (#9990417): FrenoCircuito soporta `expira_en`
opcional") — extensión aditiva, no relacionada con este item, que no cambia el comportamiento
base ya pedido aquí.

## Verificación contra el código real (`main`, hoy)

1. **Centinela en archivo, no en BD** — `app/Modules/Addons/Roadmap/Support/FrenoCircuito.php`:
   - `activo()`: la **existencia** del archivo es la pausa (`file_exists`, con `clearstatcache`
     porque el proceso que lo pone puede ser otro en el mismo segundo).
   - `poner()`: escritura **atómica** (tmp + `rename` en el mismo filesystem) — un lector nunca ve
     el centinela a medio escribir.
   - Ruta **absoluta y literal** (`/var/www/megaisp/storage/app/circuito/PAUSA`, vía
     `config('circuito.freno.centinela')`), nunca `storage_path()` — cada worktree (`wt-1`…`wt-6`)
     tiene su propio `storage/` real, así que una ruta relativa habría dado a cada terminal su
     freno privado (verificado en el propio comentario del código, fechado 2026-08-25).
   - `config/circuito.php:334` trae la clave `freno.centinela` con ese mismo default.
2. **`isPaused()` fail-closed** — `RoadmapCircuitoService.php:150-171`: mira el archivo PRIMERO
   (con soporte de expiración de #9990417), luego `settings` como respaldo, y **todo** dentro de
   un `try/catch` que devuelve `true` (frenado) ante cualquier excepción — registrando el fallo en
   **archivo** (`FrenoCircuito::registrarFallo`), nunca en BD (si la BD es el problema, escribir
   ahí el motivo sería perder el rastro).
3. **Chequeo dentro del lazo, no solo al arrancar** — confirmado que `isPaused()` se consulta en
   múltiples puntos del pool continuo (`SchedulerCommand.php:69,495`), no solo una vez al inicio
   de la vuelta — esto es lo que evita que un huérfano como el del 22-ago siga corriendo aunque se
   ponga el freno a medio camino.
4. **`setPaused()` sigue siendo solo-lectura para el ejecutor** (candado #342 intacto): solo un
   humano autenticado con `circuito.pause` puede tocar el freno; el ejecutor on-box únicamente lee.

Todo lo pedido por el item está cubierto punto por punto. Sin brechas encontradas.

## Nota sobre la rama registrada

La rama `circuito/item-170-vendedores-renderizar-saldo-null-como` ya existía en el repo desde
antes (commit huérfano `c827fffb`/`94454ef6`, jul-2026) — resto de una encarnación **anterior**
de este mismo ID de item, cuando #170 trataba un tema distinto ("Vendedores: saldos null vs $0",
ver commit `82be4096` en el histórico). Ese contenido (borrado de 2 exports muertos en
`helper.js`, sin consumidores) es válido pero **no pertenece al alcance actual** del item (que ya
es "freno de mano fuera de la BD"); se dejó fuera de este cierre para no mezclar dos asuntos bajo
un mismo commit de integración. La rama se reseteó a `main` antes de agregar este documento —el
commit viejo sigue recuperable vía `c827fffb` y la referencia remota
`refs/remotes/local/circuito/item-170-...` si alguien quiere retomarlo como item aparte.

## Conclusión

**Sin cambio de código** — el freno de mano fuera de la BD, fail-closed, ya está en `main` desde
el 2026-08-25. Se cierra el item documentando la verificación.
