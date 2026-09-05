# Item #9990370 — verificar-vuelta, Escenario 1 (estado sano), ejecución real

## Contexto

`#9990370` nació como sub-item de `#9990367` ("verificar-vuelta — Escenario 1 (estado sano)"):
`circuito:cabida` sobre `#9990367` dio **NO CABE** (ETA histórico ~937s, `eta_metodo=historico`,
dominado por el paso `migrate --dry-run` de `circuito:verificar-vuelta`, timeout interno 600s), así
que ese item no picó código y descompuso aquí la ejecución real.

Objetivo idéntico al de `#9990367`: correr `circuito:verificar-vuelta` en estado sano (sin tocar
código) y confirmar `exit 0` sin ningún paso `fail`.

## Ejecución (wt-4)

- Rama de prueba descartable creada desde `main` (`prueba/verificar-vuelta-escenario1-wt4`), código
  intacto — nunca se commiteó nada ahí.
- Comando corrido en background (nohup) por el ETA largo, con `--item=9990370 --json`:
  ```
  php artisan circuito:verificar-vuelta Talento --item=9990370 --json
  ```
- Resultado exacto (el paso `migrate --dry-run` tardó unos minutos; el resto fue casi instantáneo):
  ```json
  {"modulo":"Talento","pasos":[
    {"paso":"php -l","estado":"ok","detalle":"sin archivos .php modificados vs main"},
    {"paso":"boot (artisan --version)","estado":"ok","detalle":"Laravel Framework 10.48.4"},
    {"paso":"tests","estado":"ok","detalle":"suite: tests/Unit/Talento — pasaron"},
    {"paso":"migrate --dry-run","estado":"ok","detalle":"Sin migraciones pendientes — nada que validar. OK."}
  ],"resultado":"ok"}
  ```
- **Exit code: 0.** Los 4 pasos en `ok`, ninguno en `fail`.

## Diferencia con lo que el spec original anticipaba (documentada, no es una discrepancia real)

El spec de `#9990367`/`#9990370` (escrito antes de esta vuelta) asumía que el paso `tests` saldría
**`skip`** porque no existía `.env.testing` en este entorno dev
(`VerificarVueltaCommand::sandboxDeTestsSeguro()` exige un `.env.testing` con `DB_DATABASE` distinto
al de dev). Para cuando esta vuelta corrió el comando, **`.env.testing` ya existía** en este worktree
(`DB_DATABASE=megaisp_test`, distinto de `megaisp`) — creado por trabajo previo de esta misma familia
de items (`#990`/`#9990362`, ver sus docs de verificación) — así que `sandboxDeTestsSeguro()` devolvió
`true` y el paso `tests` **corrió de verdad** (`tests/Unit/Talento`) y pasó, en vez de saltarse. Un
`ok` real es un resultado igual de válido (o mejor) que el `skip` esperado para los fines de este
escenario: el contrato pedido era "exit 0 sin ningún fail", y eso se cumplió con margen.
`.env.testing` es local al worktree, está en `.gitignore` (`.gitignore:39`), no se commitea.

## Limpieza

- Rama de prueba (`prueba/verificar-vuelta-escenario1-wt4`) borrada tras confirmar el resultado.
- Ningún residuo de código de la app en `main`: el único cambio de esta vuelta es este documento.

## Conclusión

Escenario 1 de `circuito:verificar-vuelta` (estado sano) queda **confirmado** con ejecución real:
`exit 0`, 4/4 pasos `ok`. Este sub-item cierra; no cierra por sí solo a `#9990367` (paraguas, cierra
vía el hook de cierre en cascada al no quedarle sub-items abiertos) ni al padre raíz `#909`/`#990`
(eso lo hace `#9990364` una vez existan los 3 resultados de escenario — este, Escenario 2, y
`#9990363` Escenario 3).
