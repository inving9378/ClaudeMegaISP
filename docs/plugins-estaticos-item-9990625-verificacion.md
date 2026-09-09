# Item #9990625 — plugins JS estáticos que `mix.version()` borraba en `public/js`

## Estado al cerrar (2026-09-09)

El arreglo real **ya estaba en `main`** cuando se retomó este item (commits `b1a7a5af` y
`384e29c8`, hechos fuera del circuito on-box, `Co-Authored-By: Claude Opus 4.8`, el mismo
2026-09-08 por la tarde). Una rama previa del propio item (`wt-1`/`wt-2`, comentarios de las
12:58-13:08) había propuesto una solución distinta (mover a `public/plugins/`) que quedó
**superada** por la que ya está en `main` — mergearla habría revertido/peleado contra el fix
vigente. Se descartó esa rama vieja y se abrió una nueva desde el `main` actual (sin cambios de
código propios: el trabajo que pedía el item ya estaba hecho).

## Qué hay hoy en `main`

- `driver.min.js`, `maps.js`, `qrcode.min.js` viven en `public/vendor/js/` (commit `b1a7a5af`,
  "Fase B #9990625 (B.1)"), fuera del directorio que `mix.js()` gestiona (`public/js`).
  `public/js` **ya no tiene ningún archivo trackeado** — el build no tiene nada que borrar ahí.
- Referencias actualizadas en los 2 blades reales: `master.blade.php` (`vendor/js/driver.min.js`,
  tour de UI) y `MegaFamilia/descargar/index.blade.php` (`vendor/js/qrcode.min.js`).
- El mensaje del staging gate (`DeploymentService.php`) se reescribió en lenguaje llano (commit
  `384e29c8`).
- `driver.min.css` se quedó en `public/css/driver.min.css` (no se movió con el resto). Es el único
  cabo suelto real de la Fase B.

## Verificación de esta vuelta (Fase 3 del prompt — criterio de aceptación)

Corrida REAL `npm run prod` (vía el semáforo `deploy/circuito/npm-build.sh`, modo prod) contra el
estado actual de `main`. MD5 y `git status --porcelain` comparados antes/después:

| Archivo | Antes | Después | Sobrevive |
|---|---|---|---|
| `public/vendor/js/driver.min.js` | `6b77b834...` | `6b77b834...` | ✅ |
| `public/vendor/js/maps.js` | `fc8cd8df...` | `fc8cd8df...` | ✅ |
| `public/vendor/js/qrcode.min.js` | `517b55d3...` | `517b55d3...` | ✅ |
| `public/css/driver.min.css` | `9a794905...` | `9a794905...` | ✅ |

`git status --porcelain` limpio tras el build (sin borrados fantasma). Los 4 archivos
sobreviven un build de producción real — criterio de aceptación de la Fase 3 cumplido con el
estado actual de `main`, sin necesidad de moverlos otra vez.

Nota técnica: a diferencia de `public/js` (donde el incidente original SÍ borró los 3 JS al
correr `npm run prod` directo, sin el semáforo), `mix.sass('resources/sass/app.scss',
'public/css')` **no limpia archivos ajenos** de `public/css` — solo sobreescribe lo que él mismo
genera (`app.css`). Por eso `driver.min.css`, aunque nunca se movió, no se ha perdido en la
práctica; igual queda como candidato a moverse junto a los demás si algún día cambia la
configuración de mix (ver hallazgo de Fase 4).

## Fase 4 — otros temporales/estáticos servidos desde `public/` (solo reporte, sin tocar)

- `public/css/app_2.css`, `public/css/pdf.css` (consumido por
  `resources/views/meganet/module/vendors/pdf.blade.php`), `public/css/receipt.css` — comparten
  el mismo directorio de salida de `mix.sass()` que `driver.min.css`. Verificado en esta vuelta
  que el build real NO los toca (mismo mecanismo). Quedan fuera de alcance de este item (título
  es "plugins JS", son CSS de otros módulos); si se decide moverlos igual que los JS, es un item
  aparte.
- `/public/reportes/` (temporales HTML servidos desde public) ya está en `.gitignore:85` desde el
  hallazgo original de este mismo item — sin pendiente.
- No se encontraron más directorios/archivos servidos desde `public/` fuera de `.gitignore` en
  esta revisión.
