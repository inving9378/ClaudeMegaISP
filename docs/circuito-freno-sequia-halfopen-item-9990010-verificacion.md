# Item #9990010 — Freno de sequía N2 Fase 3a-i (RESUELTO — premisa incorrecta)

**Fecha:** 2026-09-04
**Item:** #9990010, sub-item de seguimiento de #925 ("que no cupo en una vuelta: `circuito:cabida`
devolvió NO CABE, `historico_excede_umbral`").

## Premisa del item

Pedía implementar la PRIMERA MITAD (Fase 3a-i) del mecanismo half-open del freno de sequía N2:

1. `config/circuito.php` → agregar `sequia.gasto_reintento_min` y `sequia.gasto_reintento_activo`.
2. `AuditorService.php` → import de `Carbon`, rama half-open en `gastoApagado()`, métodos privados
   `reintentoActivo()` y `venceReintento()`.
3. Explícitamente **NO tocar** `evaluarApagarGasto()` (eso quedaba para la Fase 3a-ii, sub-item
   hermano de este mismo padre #925).

## Hallazgo

Todo el mecanismo — **incluyendo la Fase 3a-ii que este item pedía dejar fuera** — ya está en
`main`, aplicado directamente bajo el propio item padre **#925**:

- Commit `da5cb758` — *"feat(circuito#925): half-open del freno de sequia N2 en AuditorService"*.
- Integrado a `main` vía `ccb52d01` — *"Integra circuito #925 ... a main"*.

Verificado contra el código real de este worktree (`git diff main` sobre
`config/circuito.php` + `app/Modules/Addons/Roadmap/Services/AuditorService.php` = vacío, cero
diferencias):

- `config/circuito.php:1180-1181` — `gasto_reintento_min` (default 30) y `gasto_reintento_activo`
  (default `true`), con el comentario `#891 Fase 3a` explicando el half-open y el fallback a
  `torre_config` cuando exista la Fase 3b.
- `AuditorService.php:10` — `use Illuminate\Support\Carbon;` ya presente.
- `AuditorService.php:246-251` — rama half-open dentro de `gastoApagado()`, idéntica a la
  especificada (`if ($this->reintentoActivo() && $this->venceReintento($desde)) { return false; }`,
  sin llamar `rearmarGasto()`).
- `AuditorService.php:294-310` — `reintentoActivo()` y `venceReintento()` privados, leyendo de
  `config()` con el mismo TODO apuntando a `torre_config` (Fase 3b) que pedía el spec.
- **Extra, más allá de lo pedido por este sub-item:** `evaluarApagarGasto()` (línea ~353) quedó
  **idempotente** (`updateOrInsert` incondicional cuando `racha >= umbral`, ya no corta si ya
  estaba apagado) — exactamente el comportamiento que este item marcaba como "Fase 3a-ii,
  sub-item hermano" y decía no tocar aquí. Y se agregó `estadoGastoUi()` (línea 263) para que la
  Fase 3b (UI, #926) lea `armado`/`disparado`/`medio_abierto` + countdown sin duplicar la lógica.

## Causa

La vuelta que descompuso #925 en sub-items (creando este #9990010 para 3a-i y un hermano para
3a-ii) lo hizo porque en ESA vuelta `circuito:cabida` había devuelto NO CABE. Pero una vuelta
posterior de **#925 mismo** sí cupo de punta a punta y resolvió el mecanismo completo en un solo
commit — sin pasar por los sub-items que había dejado registrados. Los sub-items quedaron
redundantes: el trabajo que describen ya estaba hecho por el padre antes de que este sub-item
llegara a ejecutarse.

## Verificación adicional (paso 5 del flujo — regresión)

```
php -l config/circuito.php                                    → sin errores
php -l app/Modules/Addons/Roadmap/Services/AuditorService.php → sin errores
php artisan --version                                          → bootea limpio
```

En tinker: `config('circuito.auditor.sequia.gasto_reintento_min')` = `30`,
`config('circuito.auditor.sequia.gasto_reintento_activo')` = `true` — coinciden con los defaults
que pedía el spec.

## Conclusión

**Sin cambio de código** — el mecanismo que pedía este sub-item (y el de su hermano 3a-ii) ya
está completo y mergeado a `main` bajo el commit del propio #925. Cerrado documentando el
hallazgo, siguiendo el patrón de items previos de esta misma familia (#733, #741, #830, etc.).
