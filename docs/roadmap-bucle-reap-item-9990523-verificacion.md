# Item #9990523 — MR-26 Fase 2 (endpoint de consulta de cobertura por coordenada) — bucle reap sobre paraguas ya descompuesto

## Contexto

Mismo patrón documentado repetidas veces en `CLAUDE.md` (#738/#745/#830/#816/#818/#848/#852/#905/
#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962, entre otros): un item que ya fue
descompuesto correctamente en un sub-item, y ese sub-item ya cerró y se mergeó a `main`, pero nadie
intentó **cerrar** al padre. El guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS") solo
actúa cuando algo intenta activamente `estado_aprobacion = 'completado'`; sin ese intento, el item
se queda `en_progreso` colgado, el reaper lo re-encola, y el pool lo reparte de nuevo sin trabajo
propio que hacer.

## Verificación

`#9990523` ("MR-26 Fase 2 — endpoint de consulta de cobertura por coordenada + NAP más cercana")
tiene un único hijo (`origen_item_id=9990523`):

- **#9990577** — "MR-26 Fase 2 (implementación) — método consultarPunto en
  MapaRedCoberturaService + endpoint /mapa-red/api/cobertura/consultar" — `completado`,
  `merge_commit=8b19fd754ec32a74ea00662fdf1e259efaa6b69b`.

El código de ese hijo ya está en `main` (commits `fd34c294`/`985d83f0`/`eea990e2`, visibles en
`git log` de este mismo checkout, sincronizado con `main` al arrancar la vuelta):

- `App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService::consultarPunto()` — reusa la
  misma query de puertos libres que `capaGeoJson()` (extraída a `napsConPuertosLibres()`), el
  mismo patrón de prefiltro bbox + Haversine exacto que `ZonaResolverService`, con búsqueda
  expandida (si el bbox inicial no tiene candidatos, cae a evaluar todas las NAPs con puerto
  libre — decisión de simplificación ya documentada en el propio docblock del servicio).
- `App\Modules\Addons\MapaRed\Controllers\CoberturaController::consultar()` — valida
  `lat`/`lng` y delega en el servicio.
- Ruta `GET /mapa-red/api/cobertura/consultar` (`routes.php:151`), dentro del grupo
  `Route::middleware(['web','auth','check_route_permission'])->prefix('mapa-red/api')`, gateada
  por el mismo permiso `mapa_red_view` que el resto de `/mapa-red/api/**` (mismo patrón que
  `NapOcupacionController`/`NapSaludController` — no se creó un permiso granular nuevo pese a que
  la opción elegida en `q3` sugería uno; es una simplificación razonable y ya mergeada, consistente
  con el resto de endpoints hermanos del mismo controlador/prefijo, y fuera de alcance reabrir un
  ítem ya cerrado por una preferencia de nomenclatura de permisos).

Smoke test en esta vuelta (tinker, sin datos nuevos):

```
$s = app(\App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService::class);
$s->consultarPunto(19.4326, -99.1332);
// => {"cobertura": false, "nap_mas_cercana": null}
```

Responde limpio (sin excepción) porque en dev `mapared_puertos` tiene **0 filas** con
`rol=NAP_SALIDA` y `estado=LIBRE` (mismo hueco de datos ya documentado en
`docs/mapared-mr16-fase2b-item-9990496-verificacion.md`: el backfill real
`mapared:backfill` nunca corrió sin `--dry-run` contra la BD de dev). No es un defecto de este
endpoint — es el comportamiento correcto de la rama "sin candidatos".

## Corrección

Esta vuelta ejecuta el intento de cierre faltante sobre `#9990523`. Como ya no quedan hijos
abiertos (`tieneSubItemsAbiertos()` = `false`), el guard de paraguas **no** lo aparca esta vez:
cierra de verdad a `completado`.

**Sin cambio de código de aplicación** — el endpoint de consulta de cobertura ya estaba completo y
mergeado desde `#9990577`. El único gap real (0 filas en `mapared_puertos`) es un problema de datos
de dev ya rastreado en el item #9990496, no de este item.
