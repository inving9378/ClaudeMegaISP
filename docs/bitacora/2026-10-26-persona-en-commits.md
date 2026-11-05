## 2026-10-26 13:30 — Persona en commits y sesiones: separar el resumen del día de cada quien

**Problema.** La cuenta del servidor y la de Claude las usan varias personas. Todos los commits
salían firmados "Irving MegaISP" (los 33 del 25 y 26 de octubre, incluidos los del circuito) y
todas las sesiones de Claude caían en la misma carpeta. Por eso el resumen del día de una persona
mezclaba el trabajo de todos.

**Qué se hizo.**
- `deploy/persona/persona.sh` + hook `SessionStart` en el `settings.json` local del checkout
  principal: cada sesión arranca sabiendo quién está del otro lado y git firma con su nombre
  (variables `GIT_AUTHOR_*`/`GIT_COMMITTER_*` vía `$CLAUDE_ENV_FILE`).
- La persona sale de `claude-como <persona>` (lanzador para SSH) o de la sesión tmux
  `web-<login>` de la terminal web. Si no hay ninguna, la sesión pregunta antes del primer commit y
  la declara con `persona.sh declarar`.
- Registro de sesiones por persona y dos consultas para el reporte: `persona.sh commits` y
  `persona.sh sesiones`.
- Primera persona nueva: David, correo `david@megaisp.local` (no el real, porque el repo es
  público). Irving conserva su firma de siempre.

**Hallazgo de paso.** `MEGAISP_TERMINAL_USER` no sirve para saber quién usa la terminal web: tmux
la copia de su entorno global, así que las sesiones nuevas traen el login de quien arrancó el
servidor tmux. Por ejemplo, `web-admin` decía `david_marsal`. Por eso se identifica por el nombre
de la sesión tmux.

**Verificado.** Hook probado en 8 escenarios: lanzador, terminal web con login mapeado, terminal
web con login sin mapa, SSH interactivo, `claude -p`, slot del circuito, sesión reanudada ya
declarada y persona desconocida. Además, una sesión real de Claude Code firmaba "Irving MegaISP"
antes del `declarar` y "David <david@megaisp.local>" en el comando siguiente.

**Fuera de alcance.** Los slots del circuito siguen firmando "Irving MegaISP", así que su trabajo
cae en el resumen de Irving. Darle una firma propia ("Circuito CC") es decisión de Irving.
Lo anterior al 26 de octubre no se puede separar por git.
