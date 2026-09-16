# Calibración del tope de sesión de Carlos (item #9991178)

Fase 2 del item #9991175 (terminal ttyd por usuario). Carlos usa la MISMA cuenta OAuth
compartida que las ~6 terminales del circuito y el uso manual de Irving. Sin un tope, una
sesión de Carlos podría consumir toda la cuota de la ventana de 5h y dejar sin margen al
circuito (o al revés). Este documento fija el número usado hoy y cómo recalcularlo.

## Decisión ya aprobada (q2 del padre #9991175)

La ventana de referencia es la de **sesión** (bloque rodante de ~5h que usa Anthropic para
el límite de las cuentas Claude Pro/Max), NO la diaria ni la semanal — son 3 límites
distintos y el de sesión es el más corto. El tope de Carlos se define como **40% de esa
ventana**, como número ABSOLUTO de tokens (no porcentaje relativo dinámico).

## Comando exacto usado (2026-09-16)

```bash
npx --yes ccusage@latest blocks --json -O > /tmp/ccusage_blocks.json
```

`ccusage` (https://www.npmjs.com/package/ccusage) lee TODOS los `*.jsonl` bajo
`~/.claude/projects/` (la cuenta OAuth compartida, TODAS las sesiones — incluidas las del
propio circuito en `wt-1..wt-6` y el uso manual) y los agrupa en bloques de 5h rodantes
(`blocks`, el mismo mecanismo de ventana que usa Anthropic para el límite de sesión). La
flag `-O` (`--offline`) usa precios cacheados en vez de pegarle a la API de pricing —
irrelevante aquí porque solo se usa `totalTokens`, no el costo en USD.

Resultado: 101 bloques totales en el rango `2026-08-18` → `2026-09-14` (4694 archivos
`.jsonl`, ~4 GB). De esos, 76 son bloques **completados** (ni gap sin actividad, ni el
bloque activo en curso — un bloque a medias no sirve como referencia del 100% de una
ventana llena).

## ¿Hubo alguna sesión que llegara al tope REAL de la cuenta?

No. Cuando Anthropic corta una sesión por tope de cuenta, el mensaje que Claude Code
persiste en el `.jsonl` es literalmente el string `Claude AI usage limit reached|<epoch>`.
Se buscó ese marcador exacto en el histórico completo:

```bash
grep -oh "Claude AI usage limit reached|[0-9]*" ~/.claude/projects/*/*.jsonl
```

**Cero ocurrencias reales.** (El único match fue el propio comando de búsqueda
auto-registrado en la transcripción de la sesión que hizo la búsqueda — descartado como
falso positivo.)

## Piso conservador aplicado (regla ya prevista por el item)

Sin ninguna sesión que haya tocado el tope real, el item indica usar el **máximo
observado** como piso conservador, dejando explícito que es una **cota inferior** (el tope
real de la cuenta puede ser mayor al que se ve aquí; simplemente nunca se alcanzó en las 4
semanas de histórico disponibles).

- **Bloque máximo observado:** `id 2026-09-11T21:00:00.000Z` (ventana
  `2026-09-11T21:00Z` → `2026-09-12T02:00Z`), **1,167,999,671 tokens totales**
  (6168 entries, modelos `claude-sonnet-5` + `claude-opus-4-8`). Ese día había varias
  terminales del circuito trabajando en paralelo sobre la misma cuenta — es exactamente el
  escenario de contención que el tope de Carlos busca prevenir.
- `tokens_totales_ventana_sesion = 1_167_999_671` (100% de referencia, cota inferior).
- `tope_sesion_carlos_pct = 40`
- `tope_sesion_carlos_tokens = round(1_167_999_671 * 0.40) = 467_199_868`

## Dónde vive (q3 del padre #9991175: `config/circuito.php`, NO `.env`, NO tabla BD)

```php
'terminal' => [
    'tope_sesion_carlos_pct' => (int) env('CIRCUITO_TERMINAL_TOPE_CARLOS_PCT', 40),
    'tope_sesion_carlos_tokens' => (int) env('CIRCUITO_TERMINAL_TOPE_CARLOS_TOKENS', 467199868),
    'tokens_totales_ventana_sesion' => (int) env('CIRCUITO_TERMINAL_TOKENS_VENTANA_SESION', 1167999671),
],
```

Los 3 valores tienen override opcional por `.env` (`CIRCUITO_TERMINAL_TOPE_CARLOS_PCT`,
`CIRCUITO_TERMINAL_TOPE_CARLOS_TOKENS`, `CIRCUITO_TERMINAL_TOKENS_VENTANA_SESION`) para
ajustar sin tocar código, pero el número calibrado vive commiteado en
`config/circuito.php` como default — auditable en git, con esta bitácora de cómo se
obtuvo.

## Cómo recalibrar en el futuro

Si la cuenta cambia de plan, o si el histórico ya acumula una sesión que sí llegó al tope
real:

1. Repetir el comando de arriba (`npx --yes ccusage@latest blocks --json -O`).
2. Repetir la búsqueda del marcador `Claude AI usage limit reached|` en
   `~/.claude/projects/*/*.jsonl`.
   - Si SIGUE sin haber ninguna: usar de nuevo el máximo observado entre los bloques
     completados (`isGap:false`, `isActive:false`) como cota inferior.
   - Si YA hay alguna: usar el **P95** de `totalTokens` de los bloques que sí llegaron al
     tope real (la regla original del item, ahora con datos reales), no el máximo —
     el P95 es menos sensible a un outlier de una sesión anómala.
3. Recalcular `tope_sesion_carlos_tokens = round(tokens_totales_ventana_sesion * 0.40)` (o
   el `%` que esté vigente en `tope_sesion_carlos_pct`).
4. Actualizar los defaults en `config/circuito.php` (o usar las 3 vars de `.env` para un
   ajuste temporal sin commit) y anotar la fecha/resultado en esta bitácora.
5. `php -l config/circuito.php` + `php artisan config:show circuito` (o tinker) para
   confirmar que carga sin error. **Nunca `config:cache`** con este repo (regla del
   proyecto — rompe la lectura de `.env`; ver CLAUDE.md).
