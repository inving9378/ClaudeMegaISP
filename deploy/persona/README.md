# Persona en commits y sesiones

La cuenta del servidor (`meganet`) y la de Claude las usan varias personas. Hasta el 2026-10-26
todos los commits salían firmados "Irving MegaISP", también los del circuito, así que el resumen
del día de una persona mezclaba el trabajo de todos. Con esto cada sesión de Claude Code sabe de
quién es y firma con su nombre. El resumen de cada quien sale de **sus** commits y **sus** sesiones.

## Cómo entra cada quien

| Entrada | Qué hacer | Persona |
|---|---|---|
| Terminal web (DevTools) | `claude`, como siempre | Sale del login del sistema (sesión tmux `web-<login>`) |
| SSH | `claude-como <persona>` en lugar de `claude` | La que se escribe |
| SSH con `claude` a secas | Claude pregunta "¿quién eres?" antes del primer commit y corre `persona.sh declarar <persona>` | La declarada |

Los slots del circuito y el merge-runner no se tocan: siguen firmando como hasta hoy.

## Piezas

- `deploy/persona/persona.sh`: todo el mecanismo; el encabezado del archivo explica cada parte.
- Hook `SessionStart` en `/var/www/megaisp/.claude/settings.json` (archivo local, no versionado):
  `bash /var/www/megaisp/deploy/persona/persona.sh hook`.
- `~/.config/megaisp/personas.tsv` (fuera del repo, porque trae logins del sistema): una línea por
  persona, separada por TAB: `persona`, nombre de autor, correo, logins de la terminal web
  (separados por coma). Para agregar a alguien basta con una línea nueva.
- `~/.local/bin/claude-como`: `exec bash /var/www/megaisp/deploy/persona/persona.sh lanzar "$@"`.
- Registro de sesiones: `~/.local/state/megaisp/persona/sesiones.tsv` (fecha, sesión, persona, origen, cwd).

## Para el resumen del día

```bash
bash deploy/persona/persona.sh commits <persona> [YYYY-MM-DD]    # sus commits, todas las ramas
bash deploy/persona/persona.sh sesiones <persona> [YYYY-MM-DD]   # sus sesiones con actividad desde esa fecha
```

El texto del reporte sigue en voz impersonal: la persona solo sirve para filtrar.

## Límites

- Lo anterior al 2026-10-26 no se separa por git: todo quedó firmado igual.
- El correo de cada commit se publica con el repo, que es público. Por eso las personas nuevas
  usan un correo `@megaisp.local`, no el real.
- Una sesión de SSH que se engancha a la sesión tmux de otra persona (`tmux attach -t web-…`)
  hereda la persona de esa sesión.
