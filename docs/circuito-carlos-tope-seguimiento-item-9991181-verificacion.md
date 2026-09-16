# Item #9991181 — Seguimiento de #9991178 (q1/q2 ya implementadas, q3 no aplica)

## Contexto

`#9991181` es el "seguimiento" auto-generado (`#1008`) al cerrar `#9991178` («Fase 2 — Calibrar
el tope de 40% de sesión de Carlos + config») con 3 preguntas que quedaron sin `opcion_elegida`.
Para cuando llegó a esta terminal, ya tenía las 3 respuestas registradas por
`irving:david_marsal` (log `2026-09-16T12:42:11-06:00`, `respuestas: {q1,q2,q3}`).

## Verificación por hash (`RoadmapItem::claveOpcion()`)

Las opciones no llevan un campo `hash` explícito en el JSON — la clave persistida en
`opcion_elegida` es `substr(sha1(texto_normalizado), 0, 16)` (`RoadmapItem.php:1466-1468`). Se
recalculó el hash de cada opción de las 3 preguntas y se confirmó cuál fue la elegida:

| Pregunta | Elegida | Texto |
|---|---|---|
| q1 — valor del tope | Opción 1 | "Mantener 40% como tope duro" |
| q2 — dónde vive la config | Opción 1 | "Archivo config del circuito (YAML/JSON versionado)" |
| q3 — acción al cruzar el tope | Opción 1 | "Auto-compact + continuar sesión" |

## q1 y q2: ya implementadas, sin acción pendiente

`#9991178` (mergeado, `merge_commit=31805a3f...`) ya dejó en `config/circuito.php`:

```php
'terminal' => [
    'tope_sesion_carlos_pct' => (int) env('CIRCUITO_TERMINAL_TOPE_CARLOS_PCT', 40),
    'tope_sesion_carlos_tokens' => (int) env('CIRCUITO_TERMINAL_TOPE_CARLOS_TOKENS', 467199868),
    'tokens_totales_ventana_sesion' => (int) env('CIRCUITO_TERMINAL_TOKENS_VENTANA_SESION', 1167999671),
],
```

Eso es exactamente **Opción 1 de q1** (40% como tope duro) viviendo en **Opción 1 de q2**
(`config/circuito.php`, versionado en git). Documentado además en
`deploy/README-calibracion-tope-carlos.md`. Nada que tocar.

## q3: NO aplica — choca con una decisión ya fijada y más autoritativa

q3 pregunta "¿qué acción se dispara al cruzar el tope?" con opciones genéricas de manejo de
sesión (auto-compact / cerrar-y-abrir-nueva / alertar-y-dejar-decidir) — un set de opciones que
no menciona en ningún punto "bloqueo duro". Pero esa MISMA pregunta ("qué pasa cuando Carlos
cruza el tope") **ya tiene una respuesta fijada, más arriba en la cadena y con más detalle**:

- **`#9991175`** (el item raíz, "Terminal ttyd por usuario + tope 40% ... SOLO para Romelio"),
  pregunta `q4`, ya aprobada por Irving (`irving:david_marsal`, 2026-09-16T07:18:12): **"Opción 1:
  Bloqueo duro — al alcanzar el tope, el launcher/wrapper de claude rechaza nuevas corridas...
  Irving/David siguen sin límite."**
- El propio `description` de `#9991175` es explícito y detallado: "CARLOS bloqueado", "mostrar el
  MISMO mensaje real de 'límite de uso/tokens agotados' de Claude (no un candado custom)", "Carlos
  NO debe poder quitarse el límite por ninguna vía (anti-bypass duro)". Esto es semánticamente
  incompatible con "auto-compact + continuar sesión" (que deja a Carlos seguir trabajando).
- **`#9991179`** (Fase 3+4, "Shim de claude + ledger + bloqueo duro + pruebas", `aprobado_revisor`,
  sin reclamar aún — bloqueado hasta que Fase 1 esté activada con root) repite la misma decisión,
  ya como spec de construcción: *"DECISIONES YA APROBADAS POR IRVING (q4, q5, q6 del item padre
  #9991175): q4: Bloqueo DURO al llegar al tope (no aviso-y-sigue, no solo log)."*

`#9991178` (el item padre inmediato de este seguimiento) tenía SOLO 5 pasos concretos en su
`description` — calibrar el número, escribirlo en `config/circuito.php`, documentar el README —
**nunca pidió implementar una acción de enforcement**. Las 4 preguntas que le quedaron adjuntas
(`q1`-`q4`, con `q4` interno = "¿cómo validar el tope calibrado?", `requiere_irving:false`) tienen
toda la pinta de una plantilla genérica de "decisiones de tope de sesión" adjuntada al crear el
item, sin adaptarla al hecho de que la Fase 2 real de este trabajo (Calibración) ya no necesitaba
decidir "qué acción se dispara" — esa decisión es exactamente el contenido de la Fase 3+4
(`#9991179`), que ya la trae resuelta y con más contexto (mensaje real + anti-bypass, Fase 5 en
`#9991180`).

## Decisión (consultado con Thomas, `circuito:consultar`, PROCEDE opción A)

La decisión vigente para "qué pasa cuando Carlos cruza el tope" sigue siendo **Bloqueo duro**
(`#9991175` q4, repetida en el spec de `#9991179`). El `opcion_elegida` de q3 en `#9991181` queda
tal cual quedó registrado (no se edita retroactivamente una respuesta de Irving/David), pero **no
se propaga a `#9991179`** ni se traduce en código — sería contradecir la decisión más detallada y
más reciente en la cadena de autoridad (item raíz → item de build), y el propio spec de `#9991179`
ya no necesita nada de esta pregunta para poder construirse cuando le toque.

Si Irving/David de verdad quisieran cambiar la política de "bloqueo duro" a "auto-compact", eso
se decide editando `#9991179` (o `#9991175` q4) directamente — no vía una pregunta de seguimiento
de la Fase 2 de calibración, que nunca tuvo ese alcance.

## Resultado

**Sin cambio de código.** `#9991178` ya entregó su trabajo real (calibración + config,
verificado). El resto (Fase 3+4 con la decisión de enforcement correcta) sigue en `#9991179`,
bloqueado hasta que Fase 1 (`#9991177`, requiere root) esté activada.
