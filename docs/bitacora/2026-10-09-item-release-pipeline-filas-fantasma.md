## 2026-10-09 — Causa raíz de "el versionado no está funcionando bien": filas fantasma por terminal compartida

### Contexto

Irving, tras pedir la versión V1.55: "verifica porque la parte del versionado no está
funcionando bien".

### Diagnóstico (`php artisan releases:reconciliar`)

De 108 versiones cruzadas contra git, **57 están "SOLO_TABLA"** (fila en `releases` sin tag real
ni en git local ni en GitHub). La gran mayoría son del esquema de versión viejo
(`2026.06.13.1`, etc.) — anteriores por completo al pipeline actual, cruft histórico sin relación
con esto. Pero **4 son recientes y del esquema actual** (`V1.51-03.10.2026`, `V1.44-28.09.2026`,
`V1.40-22.09.2026`, y el intento fallido de `V1.44-29.09.2026` antes de su reintento exitoso).

Revisando `deployment_logs` de esas 4: **las cuatro fallaron en el mismo paso exacto**,
`git_staging_gate`, con el mismo mensaje — "el proyecto tiene cambios sin guardar que no forman
parte de la publicación". Es decir: alguien le dio clic a "Nueva versión" en el momento exacto en
que OTRA terminal (este checkout se comparte entre varias sesiones de trabajo a la vez) tenía
cambios sin commitear sueltos en el árbol — el candado de seguridad (que existe justo para no
publicar algo por error) hizo bien su trabajo y frenó el pipeline. El problema real: **el
`Release` y el `DeploymentLog` ya se habían creado en `ReleaseController::store()` ANTES de correr
el pipeline que los iba a rechazar** — así que el frenazo dejaba una fila fantasma registrada en
BD en vez de simplemente no crear nada.

Esto explica lo que Irving percibía como "no funciona bien": la lista de versiones acumula
entradas fallidas/confusas (a veces el mismo número de versión aparece dos veces, una fantasma y
una real con fecha distinta) cada vez que coincide mal el timing entre dos terminales.

### Fix

`DeploymentService::arbolListoParaRelease()` (nuevo, público) — reusa TAL CUAL las mismas dos
validaciones que ya corrían dentro del pipeline (`executeSecretCheck()`/`executeStagingGate()`,
sin tocarlas), pero ahora también se puede llamar ANTES de escribir nada en BD.
`ReleaseController::store()` la corre primero; si el árbol está sucio, responde 422 con el mismo
mensaje de siempre y **no crea ninguna fila**. El paso del pipeline se deja intacto como red de
seguridad para la ventana (normalmente segundos) entre este chequeo y que el job realmente corra
— sigue pudiendo pasar en teoría, solo que ahora es mucho más raro.

### Verificación

- Árbol limpio → `arbolListoParaRelease()` devuelve `null`.
- Árbol con un archivo suelto no permitido → devuelve el mensaje correcto.
- Llamada real a `store()` con árbol sucio → **HTTP 422, cero filas nuevas** en `releases` ni en
  `deployment_logs` (antes/después iguales: 107/107 y 79/79).
- Llamada real a `store()` con árbol limpio → HTTP 200, fila creada, pipeline despachado
  normalmente (camino feliz intacto).
- ⚠️ Nota operativa de la propia verificación: las dos pruebas de "versión de prueba" que
  disparé por error alcanzaron a encolar el job real de deploy (una incluso con número de versión
  que habría generado un tag/release REAL en GitHub) — se detectaron y se borraron el job en cola
  y las filas de BD **antes de que el worker las tomara**. Confirmado sin rastro: ningún tag
  `V1.99*` ni local ni en `origin`, sin jobs ni failed_jobs nuevos. Lección para la próxima vez
  que se pruebe `ReleaseController::store()` directo: usar un número de versión que de verdad no
  vaya a importar, y limpiar la cola de inmediato, no solo las filas de BD.

### Estado

Mergeado a `main`. Las 57 filas fantasma históricas (viejas, esquema de versión anterior) no se
tocaron — limpieza de datos aparte, fuera de alcance de este fix (que es sobre el mecanismo, no
sobre el historial).
