# Mensaje real de límite de uso — Fase 5 parte 1 (item #9991180, sub-item de #9991175)

Decisión ya aprobada por Irving (ampliación de David, 2026-09-16, parte del prompt del
padre #9991175): al bloquear a Carlos por tope de sesión, el mensaje debe verse **idéntico**
al que Claude Code muestra cuando la cuenta real topa su límite de uso — no un candado
custom con texto propio.

Este documento cubre solo la **parte 1** del item (captura del texto real). La parte 2
(anti-bypass duro) queda en su propio sub-item, bloqueada hasta que la Fase 1 (#9991177)
esté activada de verdad en el servidor (paso root, no solo el código mergeado).

## Regla seguida (c) del prompt del item

No se provocó el límite a propósito con la cuenta compartida — eso habría sido gasto caro
solo para capturar un texto, y contradice la Fase 6 del padre (avisar antes de gasto alto).
En su lugar se buscó en el histórico local de sesiones (`~/.claude/projects/*/*.jsonl`) un
evento que **ya** hubiera topado el límite real, sin necesidad de generarlo.

## Comando usado (2026-09-16)

```bash
grep -rlio "claude usage limit reached[^\"]*\|usage limit reached[^\"]*\|your limit will reset[^\"]*" ~/.claude/projects/*/*.jsonl
```

Un solo hit real y genuino en todo el histórico local:
`~/.claude/projects/-var-www/ad06b1ec-f9d6-470e-867d-4dcd86600c09.jsonl` — sesión de uso
manual de Irving en `/var/www`, Claude Code **v2.1.270**, `2026-09-15T12:08:37.448Z`.

(El resto de los "hits" que arrojó una búsqueda más amplia por `usage limit`/`quota` eran
falsos positivos: el propio texto del prompt de este item citándose a sí mismo en sesiones
de investigación anteriores, o el comando de búsqueda auto-registrado en su propia
transcripción.)

## Evidencia cruda — dos mensajes reales, de la misma cadena de eventos

Cuando la cuenta topó su límite, Claude Code escribió **dos** entradas distintas en el
`.jsonl` (líneas 21-22 de esa sesión, correlativas):

### 1) Mensaje sintético del propio CLI (no una respuesta real del modelo)

```json
{
  "type": "assistant",
  "message": {
    "model": "<synthetic>",
    "stop_reason": "stop_sequence",
    "content": [
      {
        "type": "text",
        "text": "You've hit your monthly spend limit · raise it at claude.ai/settings/usage?from=cc_cli_limit_message · your weekly limit resets 10pm (America/Mexico_City)"
      }
    ]
  },
  "quotaLimits": {
    "status": "rejected",
    "resetsAt": 1789531200,
    "rateLimitType": "seven_day",
    "overageStatus": "rejected",
    "overageDisabledReason": "org_level_disabled_until",
    "isUsingOverage": false
  },
  "error": "rate_limit",
  "isApiErrorMessage": true,
  "apiErrorStatus": 429
}
```

Texto (sin escapar): `You've hit your monthly spend limit · raise it at claude.ai/settings/usage?from=cc_cli_limit_message · your weekly limit resets 10pm (America/Mexico_City)`

Nota: este ejemplo real topó el límite **semanal** (`rateLimitType:"seven_day"`), no el de
**sesión** de 5h que aplica al tope de Carlos (#9991178) — el wording menciona "monthly
spend limit" y "weekly limit" en el mismo mensaje (aparente inconsistencia del propio CLI,
no un error de esta captura: es lo que el sistema realmente escribió). El **mecanismo**
(mensaje sintético `model:"<synthetic>"`, `error:"rate_limit"`, `apiErrorStatus:429`,
`quotaLimits` con `resetsAt`/`rateLimitType`) es el mismo para cualquier tipo de límite;
solo cambia qué período nombra el texto y qué trae `rateLimitType` (`five_hour` sería el
que correspondería a la ventana de sesión, no observado en el histórico local — ver
`deploy/README-calibracion-tope-carlos.md`, que confirmó 0 sesiones tocaron el tope real).

### 2) Aviso de sistema que sigue inmediatamente (esto es lo que se ve literal en pantalla)

```json
{
  "type": "system",
  "subtype": "informational",
  "content": "Usage limit reached · continuing automatically at 10pm · esc or type to cancel",
  "level": "notice",
  "entrypoint": "cli"
}
```

Texto (sin escapar): `Usage limit reached · continuing automatically at 10pm · esc or type to cancel`

Este es el texto elegido para `config('circuito.terminal.mensaje_limite_sesion.aviso')` — es
el aviso genérico (no menciona el tipo de período), el que de verdad aparece en la terminal,
y el más corto/reusable para el shim de Carlos.

## Por qué esto CORRIGE el supuesto de #9991178

El comentario de calibración de `config/circuito.php` (item #9991178) asumía que "el mensaje
que Claude Code persiste en el `.jsonl` es literalmente el string
`Claude AI usage limit reached|<epoch>`" — pero esa búsqueda dio **0 ocurrencias genuinas**
en el mismo histórico. La búsqueda de este item, con un patrón más amplio, sí encontró un
evento real: la forma real no es ese marcador con pipe, sino los dos mensajes de arriba
(`type:"assistant"` sintético + `type:"system"` informational). No se editó el comentario de
#9991178 (pertenece a otro item ya mergeado) — queda esta nota como referencia cruzada para
quien lo lea después.

## Qué debe hacer el shim (#9991179) con esto

Leer `config('circuito.terminal.mensaje_limite_sesion.aviso')` (plantilla `sprintf`, un solo
`%s` para la hora) y `config('circuito.terminal.mensaje_limite_sesion.formato_hora')`
(formato `date()` de PHP: `'ga'` → `g` hora 12h sin cero a la izquierda + `a` am/pm), calcular
la hora real de reinicio de la ventana de 5h desde el ledger de Fase 3+4, y componer:

```php
sprintf(
    config('circuito.terminal.mensaje_limite_sesion.aviso'),
    date(config('circuito.terminal.mensaje_limite_sesion.formato_hora'), $resetTimestamp)
);
```

**No inventar un texto propio ni reformular el wording** — es justo lo que este item existe
para evitar.

## Variantes conocidas — lo que falta por confirmar

- Solo hay **un** ejemplo real disponible, y cayó justo en una hora en punto ("10pm", sin
  minutos). No hay evidencia local de cómo se ve el aviso cuando el reinicio cae a media
  hora (ej. "10:30pm" vs "10pm" truncado) — si algún día aparece un segundo ejemplo real con
  minutos != 00, ajustar `formato_hora` en consecuencia.
- No hay ningún ejemplo real capturado del tipo `rateLimitType:"five_hour"` (el que
  correspondería exactamente a la ventana de sesión de Carlos) — el ejemplo disponible es
  `"seven_day"`. Se usa igual porque el aviso genérico (mensaje 2) no varía por tipo de
  período; si en el futuro aparece un ejemplo real de `five_hour`, confirmar que el aviso 2
  es idéntico (se espera que sí, porque es el mismo mecanismo de "continuing automatically").
- No se investigó el comportamiento en modo no-interactivo (`--print`/SDK) — el ejemplo
  capturado es de una sesión CLI interactiva (`entrypoint:"cli"`). El shim de Carlos corre
  como wrapper de proceso, no dentro de una sesión interactiva real, así que de todos modos
  va a **imprimir** este texto él mismo (no a dejar que el `claude` real lo genere) — la
  duda de "cómo se ve en modo no-interactivo" es irrelevante para lo que el shim necesita.

## Cómo repetir esta investigación

```bash
grep -rlio "usage limit reached[^\"]*\|your limit will reset[^\"]*" ~/.claude/projects/*/*.jsonl
```

Si aparece un ejemplo nuevo (sobre todo uno con `rateLimitType:"five_hour"` o con minutos
distintos de 00), actualizar `mensaje_limite_sesion` en `config/circuito.php` y esta
bitácora con la fecha y el hallazgo.
