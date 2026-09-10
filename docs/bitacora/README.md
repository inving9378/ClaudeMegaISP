# Bitácora — un archivo por item

```
docs/bitacora/<YYYY-MM-DD>-item-<id>.md
```

Cada tarea grande escribe su reporte aquí, en **su propio archivo**. Dentro, el formato de
siempre: `## YYYY-MM-DD HH:MM — <título>`, y APPEND si se retoma el mismo item el mismo día.

Si el trabajo no tiene item del roadmap, un slug descriptivo sirve igual:
`2026-09-10-auditoria-asterisk.md`.

## Por qué cambió (2026-09-10)

Antes todo iba a `docs/bitacora-sesiones.md`, un archivo único. Con varias terminales trabajando
en paralelo eso significa que **cada rama toca el mismo archivo**, y por lo tanto **cada merge
conflictúa ahí** — en el único archivo que ninguna rama necesita realmente compartir.

Un archivo nuevo por item no conflictúa nunca: ninguna otra rama lo tiene. Git no tiene con qué
chocar.

Y buscar qué pasó con un item pasa a ser abrir su archivo, en lugar de recorrer un log común que
ya iba por 4,263 líneas y 126 entradas.

## El histórico se conserva

`docs/bitacora-sesiones.md` **queda como está**. No se migra, no se parte, no se borra: todo lo
escrito hasta el 2026-09-10 sigue ahí y se sigue consultando. Solo se dejó de escribir en él.

Partirlo en 126 archivos habría reescrito historia por una ganancia nula — el problema eran los
conflictos futuros, no los registros pasados.
