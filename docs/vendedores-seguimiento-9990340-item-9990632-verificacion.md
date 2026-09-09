# Item #9990632 — Seguimiento de la pregunta sin resolver de #9990340 (RESUELTO — pregunta ya respondida en el padre)

## Contexto

Cadena de seguimientos automáticos, misma pregunta textual repetida en 3 generaciones:

> ¿Las pantallas de Vendedores `/sellers/prospectos` y `/sellers/statistics` se reconstruyen (y
> con qué contenido), o se retiran los enlaces del menú?

- **#269** (original) — "Vendedores: `/sellers/prospectos` y `/sellers/statistics` no existen
  (404 desde el menú)". Se cerró con esta pregunta `requiere_irving` sin `opcion_elegida`.
- **#9990340** ("Seguimiento: pregunta sin resolver de #269") — resuelta el 2026-09-04 por
  `wt-6`: la pregunta **ya estaba respondida por la implementación** del propio #269 — los
  enlaces del menú se repuntaron a rutas reales (`/vendedores/prospectos` y
  `/vendedores/dashboard`), **no se reconstruyeron pantallas nuevas ni se retiraron los
  enlaces**. Esa vuelta además encontró y corrigió un residuo real no cubierto por el fix
  original: el catálogo `screens[]` de `module.json` seguía apuntando a la URL muerta
  `/sellers/statistics`; lo corrigió a `/vendedores/dashboard`. Mergeado a `main`
  (`b8a11e1a`), aprobado por Irving.
- **#9990632** (este item, "Seguimiento: pregunta sin resolver de #9990340") — el generador
  automático de seguimientos (`RoadmapItem::saving()`, hook #1008) volvió a crear un hijo
  porque el campo estructurado `preguntas[0].opcion_elegida` de #9990340 seguía en `null`,
  aunque el `reporte_coloquial` del propio #9990340 ya traía la respuesta completa. Es la
  **misma carrera de timing** ya documentada varias veces en el histórico del circuito
  (`docs/inventario-seguimiento-218-item-733-verificacion.md`,
  `docs/inventario-seguimiento-733-item-741-verificacion.md`,
  `docs/inventario-seguimiento-741-item-753-verificacion.md`,
  `docs/circuito-mr36-seguimiento-item-9990385-verificacion.md`): el generador lee el arreglo
  `preguntas[]` sin mirar si el `reporte_coloquial`/`comentarios_claude` del padre ya contiene
  la respuesta.

## Por qué no aplica el guard de `cadenaSeguimientoRepetida()` (#753)

El guard que corta la cadena en la 3ª repetición idéntica (`JarvisService::cadenaSeguimientoRepetida()`,
agregado en #753) solo actúa a partir de **3 generaciones** con la misma pregunta textual. Aquí
la cadena es #269 (gen 0) → #9990340 (gen 1) → #9990632 (gen 2) — solo 2 generaciones, por
debajo del umbral. El guard funcionó exactamente como fue diseñado; no hace falta tocarlo.

## Verificación directa contra el código real de dev (2026-09-08)

Confirmado que la respuesta de #9990340 sigue vigente y aplicada en `main`:

```
$ grep -n "url" app/Modules/Addons/Vendedores/module.json | grep -i "prospectos\|dashboard\|statistics"
"label": "Prospectos",   "url": "/vendedores/prospectos",   "permission": "seller_view_prospects"
"url": "/vendedores/prospectos"
"label": "Estadísticas", "url": "/vendedores/dashboard",    "permission": "seller_view_statics"
```

- Ningún `screens[]`/`menu[]` del `module.json` de Vendedores referencia ya `/sellers/prospectos`
  ni `/sellers/statistics` (`grep` sin resultados en todo el archivo).
- `grep -rn "sellers/prospectos\|sellers/statistics"` sobre `app/` y `resources/` completo:
  **cero coincidencias** en todo el repo.
- `php artisan route:list` confirma que las rutas reales usadas por el menú **sí existen y
  responden**: `GET vendedores/prospectos` (`prospectos.index`), `GET vendedores/dashboard`
  (`dashboard`), `GET sellers/seller` (listado de vendedores) — ninguna 404.

## Conclusión

#9990632 no trae información nueva: la pregunta de #269 quedó resuelta desde #9990340
(reconstruir vs. retirar → **ninguna de las dos**, se repuntaron a rutas reales ya existentes) y
sigue aplicada sin regresiones. Se cierra el hilo fijando `preguntas[0].opcion_elegida` con la
respuesta real, igual que los cierres anteriores de esta familia. **Sin cambio de código de
negocio** — el trabajo real (repuntar enlaces + corregir el catálogo de ayuda) ya estaba mergeado
desde #9990340.
