# Item #9990435 — `poner()` con `expira_en` opcional (FrenoCircuito) — implementación (RESUELTO — ya implementado)

**Sub-item de seguimiento de #9990430**, con el mismo spec exacto que su padre: cambiar la firma de
`poner()` a `poner(string $motivo, string $quien, ?string $expiraEn = null): void` y, si
`$expiraEn !== null`, agregar `'expira_en' => $expiraEn` (ISO8601 tal cual, sin reformatear) al array
`$carga` antes del `json_encode`. Con `null` el JSON debía quedar byte-idéntico al actual.

## Hallazgo

Al llegar a este item, `app/Modules/Addons/Roadmap/Support/FrenoCircuito.php` **ya tiene exactamente
ese cambio** — commit `d40eb231` ("FASE 4a (#9990417): FrenoCircuito soporta expira_en opcional"),
integrado a `main` vía `7b74322e` ("Integra circuito #9990417 ... a main") a las 17:06, junto con el
método `expirado()`.

Carrera de timing, un nivel más abajo de la misma cadena que ya documentó **#9990430** en
`docs/frenocircuito-expira-en-item-9990430-verificacion.md`: el propio #9990430 llegó a la misma
conclusión ("ya implementado", sin cambio de código) y se cerró a las 17:15 — pero #9990435 ya había
nacido como su sub-item de seguimiento a las 17:12 (antes de que #9990430 terminara de verificarlo),
así que heredó la misma pregunta ya resuelta. Mismo patrón que #733/#741/#753/#9990003/#9990353/#9990430
documentados en `CLAUDE.md`.

## Verificación (sin cambio de código)

- `php -l` del archivo: limpio.
- Ningún llamador real pasa 3er argumento (confirmado por grep): `PausarCommand.php:50`,
  `RoadmapCircuitoService.php:197`, `JarvisVigilarCommand.php:101,112` — los 4 siguen con 2
  argumentos, retrocompatibles.
- Prueba en tinker con `config(['circuito.freno.centinela' => $tmpPath])` (path temporal, NUNCA el
  centinela real):
  - Sin `$expiraEn` → JSON `{"motivo":...,"quien":...,"cuando":...}` **sin** la key `expira_en` (OK).
  - Con `$expiraEn` pasada (`2020-01-01...`) → la key viaja **tal cual** (sin reformatear) y
    `expirado()` devuelve `true`.
  - Con `$expiraEn` futuro (`2099-01-01...`) → `expirado()` devuelve `false`.

Los 3 casos que pedía el item verificados correctos. **Sin cambio de código** — el trabajo ya estaba
aplicado en `main` antes de que esta terminal reclamara el sub-item.
