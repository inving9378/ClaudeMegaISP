# Item #229 — Extender la API roadmap-externo (Opción 2) — verificación

## Premisa del item

El item #229 (creado 2026-08-26) pedía extender `/api/roadmap-externo/{token}` con alta de
items e historial de reportes, vía un `RoadmapIntakeService` compartido, sin tocar `guard()`
ni permitir que el token externo apruebe niveles B/C.

## Hallazgo

Esa misma "Opción 2 pendiente del circuito" **ya estaba implementada y en producción**
desde el commit `fef247e9` (2026-08-08 — 18 días antes de que se creara este item),
título literal: *"feat(circuito): API del Roadmap extendida — crear items, historial de
reportes y estado de cola"*. El item #229 describe, casi palabra por palabra, un trabajo que
el propio autor ya había cerrado antes de redactarlo (probablemente reutilización de un id
de item: el commit de merge histórico `0abdf03d` para "#229" corresponde a un item totalmente
distinto — "liga pública de enrolamiento de tarjetas" — señal de que el contador de ids se
reinició o se reusó en algún punto).

Verificado en el código vivo de este worktree (rama sobre `main`, sin diffs pendientes antes
de esta sesión):

- `app/Modules/Addons/Roadmap/Services/RoadmapIntakeService.php` — punto único de alta,
  compartido por la vía externa, las terminales (`circuito:sub-item`) y Jarvis. Candado
  duro: el item nace `pendiente_revision` sin excepción (`estado_aprobacion` fijo en el
  método `crear()`, sin parámetro que lo cambie) y el `nivel_riesgo_origen` se sella según
  `$interno` (así el guard #260 sigue sin dejar que un origen externo alcance
  `aprobado_claude` vía nivel A).
- Rutas de alta: `POST /{token}/item` (`createItem`) y
  `GET /{token}/crear/{modulo}/{titulo_b64}/{spec_b64?}` (`createItemPathB64`, para el
  fetcher de Cowork que solo hace GET y descarta el query string) — ambas en
  `RoadmapExternalController` + `routes.php` del módulo, con token propio `create_token`
  (cae a `write_token` si no está definido en `.env`) y tope diario (`max_items_dia`).
- Historial append-only: tabla `roadmap_item_reports`
  (`app/Modules/Addons/Roadmap/migrations/2026_08_08_120000_create_roadmap_item_reports_table.php`,
  1849 filas ya escritas en dev — en uso real por las terminales), modelo
  `RoadmapItemReport`, servicio `RoadmapReportService`. Endpoints
  `POST /{token}/item/{id}/reporte` y `GET /{token}/item/{id}/historial` (este último con
  el token de LECTURA, no el extendido — correcto: leer el rastro no requiere el scope de
  escritura).
- `guard()` de `RoadmapCircuitoService` **no fue tocado** por esa extensión (verificado que
  la vía externa de creación/reporte no pasa por él en absoluto — solo el `writeItem()` de
  los 3 campos acotados lo usa, sin cambios).

## Lo único que faltaba: el manual auto-servido no mencionaba los endpoints nuevos

El paso 4 del item ("Manual servido en el GET, actualizado") sí tenía un hueco real: el
bloque `_ayuda` que devuelve el GET por defecto (`RoadmapExternalController::ayuda()`) solo
documentaba los endpoints de lectura/listado (`parametros`, `ejemplos`, `variante_path`) —
nunca mencionaba `POST /{token}/item`, la variante `/crear/...` en base64url, `POST
.../reporte` ni `GET .../historial`, pese a que los cuatro llevan meses viviendo y
funcionando. Un consumidor que solo lee la autodocumentación del GET no los descubría.

**Fix aplicado (este item):** se agregó la clave `escritura_extendida` a `ayuda()` con los
cuatro endpoints y su forma de uso, mismo estilo que las claves existentes. Sin cambio de
lógica, sin tocar `guard()`, sin nuevas rutas — solo la autodocumentación quedó al día con
lo que el sistema ya hace.

## Conclusión

Item #229 se cierra como **ya resuelto en lo sustantivo** (código de meses atrás) + el gap
real de documentación cerrado en esta vuelta. No aplica ninguna de las tres opciones A/B/C
del brief de riesgo del revisor (crear más superficie, refactor de `guard()`, etc.) porque
la superficie ya existe y está probada; no había nada que decidir de diseño.
