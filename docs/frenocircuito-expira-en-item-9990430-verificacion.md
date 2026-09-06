# Item #9990430 — `FrenoCircuito::poner()` con `expira_en` opcional (RESUELTO — ya implementado)

**Sub-item de seguimiento de #9990424** (a su vez sub-item de #9990417, FASE 4a), con spec exacto:
cambiar la firma de `poner()` a `poner(string $motivo, string $quien, ?string $expiraEn = null): void`
y, si `$expiraEn !== null`, agregar `'expira_en' => $expiraEn` (ISO8601 tal cual, sin reformatear) al
array `$carga` antes del `json_encode`. Con `null` el JSON debía quedar byte-idéntico al actual.

## Hallazgo

Al llegar a este item, `app/Modules/Addons/Roadmap/Support/FrenoCircuito.php` **ya tiene exactamente
ese cambio** — commit `d40eb231` ("FASE 4a (#9990417): FrenoCircuito soporta expira_en opcional"),
integrado a `main` vía `7b74322e` ("Integra circuito #9990417 ... a main"), junto con el método
`expirado()` que este mismo sub-item dejaba como base para el siguiente paso.

Carrera de timing: el item padre **#9990417** se descompuso en sub-items (#9990424 → #9990430 +
#9990431) porque no cabía en una vuelta, pero **otra sesión terminó implementando la FASE 4a
completa directamente sobre #9990417** antes de que el sub-item #9990430 llegara a ejecutarse. Mismo
patrón que #733/#741/#753/#9990003/#9990353 documentados en `CLAUDE.md`.

## Verificación (sin cambio de código)

- `php -l` del archivo: limpio.
- Ningún llamador real pasa 3er argumento (confirmado por grep): `PausarCommand.php:50`,
  `RoadmapCircuitoService.php:197`, `JarvisVigilarCommand.php:101,112` — los 4 siguen con 2
  argumentos, retrocompatibles.
- Prueba en tinker con `config(['circuito.freno.centinela' => $tmpPath])` (path temporal, NUNCA el
  centinela real):
  - Sin `$expiraEn` → JSON `{"motivo":...,"quien":...,"cuando":...}` **sin** la key `expira_en` (OK).
  - Con `$expiraEn` pasado (`2020-01-01...`) → la key viaja **tal cual** (sin reformatear) y
    `expirado()` devuelve `true`.
  - Con `$expiraEn` futuro (`2099-01-01...`) → `expirado()` devuelve `false`.

Los 3 casos que pedía el item verificados correctos. **Sin cambio de código** — el trabajo ya estaba
aplicado en `main` antes de que esta terminal reclamara el sub-item.
