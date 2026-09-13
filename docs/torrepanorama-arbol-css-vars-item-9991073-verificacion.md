# Item #9991073 — Fase A CSS vars `--pa-warn`/`--pa-danger` + fix `.pa-error-raiz` (RESUELTO — ya aplicado)

## Contexto

`#9991073` es un sub-item de seguimiento de `#9991072` (que a su vez es sub-item de `#9991071`,
"Torre / Panorama árbol: ilegible en modo oscuro"). Su spec pedía, en
`resources/js/components/module/releases/torre-control/TorrePanoramaArbol.vue`:

- (A) dentro de `.pa-wrap` (línea ~393): agregar `--pa-warn:#d97706; --pa-danger:#dc2626;`
- (B) dentro de `.pa-wrap.pa-dark` (línea ~398): agregar `--pa-warn:#fbbf24; --pa-danger:#f87171;`
- (C) línea ~425: `.pa-error-raiz{ color:#dc2626; }` → `color:var(--pa-danger,#dc2626);`

## Hallazgo

Al leer el archivo real (2026-09-13), **las tres piezas ya estaban aplicadas**:

```
.pa-wrap{
  ...
  --pa-danger:#dc2626; --pa-warn:#d97706;
  ...
}
.pa-wrap.pa-dark{
  ...
  --pa-danger:#f87171; --pa-warn:#fbbf24;
  ...
}
...
.pa-error-raiz{ color:var(--pa-danger,#dc2626); font-size:13px; }
```

Valores idénticos a los pedidos (mismo par claro/oscuro que `TorreTerminales.vue` usa para
`--tt-warn`/`--tt-danger`, tal como pedía el propio spec).

## Causa (carrera de timing, mismo patrón documentado en `CLAUDE.md`)

Secuencia real por timestamp:

1. `#9991071` creado 10:29:05 (reporte del bug de contraste).
2. `#9991072` creado 10:39:19 como sub-item ("aplicar el fix, diff ya identificado").
3. `#9991073` (este item) creado 10:43:55 como sub-item de `#9991072`, junto con su hermano de
   Fase B (fix en `TorreArbolNodo.vue`) — la vuelta que trabajaba `#9991072` decompuso el trabajo
   por archivo antes de aplicarlo ella misma.
4. Commit `2f6b2cf2` ("fix(torre-control): corrige contraste del árbol en modo oscuro") a las
   **10:44:08** — un minuto después de crear los sub-items — aplicó el fix COMPLETO (ambos
   archivos, todos los selectores) directo en la rama de `#9991071`.
5. `d4867cf8` integró `#9991071` a `main` a las 10:46:04, con el fix ya incluido.

Es decir: el fix se terminó de escribir y mergear a `main` **después** de que se crearan los
sub-items de seguimiento, por lo que estos nacieron con un trabajo que ya estaba resuelto en
`main` en cuanto se reclamaron. Mismo patrón de carrera de timing ya documentado repetidas veces
en `CLAUDE.md` (`#733`/`#741`/`#753`/`#9990003`/`#9990353`/`#9990658`).

## Verificación

- `grep` directo de las 3 piezas en `TorrePanoramaArbol.vue`: presentes, valores exactos.
- Sin diferencias de contenido entre lo pedido y lo que hay en `main` — no hace falta ningún
  cambio de código.

## Resultado

**Sin cambio de código** — el fix ya estaba aplicado por el commit `2f6b2cf2`, mergeado a `main`
antes de que se reclamara este item. Cierre documental únicamente.
