# Runbook — OLT Huawei, Fase A (item #281): escritura real en laboratorio

> **Frontera dura.** Esta guía es para la sesión **presencial con Irving**. Nada de lo descrito
> aquí se ejecuta desde una vuelta autónoma del circuito. Ver `docs/MULTIOLT_SAAS_DISENO.md` §9
> y `docs/AUDITORIA_OLT_MULTIMARCA_2026-07-15.md` líneas 165-166/224-225: cualquier write nuevo
> se prueba primero contra OLT de laboratorio **con Irving presente**, condición no negociable
> (decisión #415).

## 0. Alcance de este runbook

Fase A únicamente: `authorizeOnu`, `deauthorizeOnu`, `setOnuEnabled` reales contra la OLT de
laboratorio. **Fase B (`setOnuSpeedProfile`, service-ports/VLANs por perfil) NO está en alcance**
— hoy `setOnuSpeedProfile()` lanza `WriteNotEnabledException('setOnuSpeedProfile')`
(`app/Services/OltDriver/Huawei/HuaweiDriver.php:844-847`), no hay código que probar todavía.

## 1. Estado verificado antes de esta sesión (preparado por el circuito, 2026-08-28)

- `HuaweiDriver::$dryRun` sigue en `true` por defecto (`HuaweiDriver.php:58`) — sin cambios.
- `ReadOnlyGuard::WRITE_ALLOW_LIST` sigue con **un solo SN**: `HWTCFEFCC9A2`
  (`ReadOnlyGuard.php:85`) — sin cambios. Ninguna SN de producción está en la lista.
- `tests/Unit/OltDriver/*` — **345 tests verdes** (1566 assertions), reverificado el 2026-08-28
  (`php artisan test tests/Unit/OltDriver/`). El item mencionaba 329; el número creció con W3.
- OLT de laboratorio configurada en `.env`: `OLT_HUAWEI_HOST=10.50.11.2`, `OLT_HUAWEI_PORT=23`,
  `OLT_HUAWEI_USER=smartoltusr` (password en `.env`, no se reproduce aquí). Alcanzabilidad de red
  **no verificada** por el circuito (tocar la OLT, aunque sea con `display`, ya es un byte hacia
  hardware real — se deja para la sesión con Irving).
- ONT de prueba: **SN `HWTCFEFCC9A2`**, `onuId = '1:0/3/2:0'` (formato `{oltId}:{frame}/{slot}/{port}:{ontId}`
  → frame=0, slot=3, port=2, ont_id=0). Es el mismo ONT que W3 ya usó para el write cosmético real
  (`descOnt`), backup de referencia en `docs/multiolt-w3-backups/w3_backup_20260616_165715.txt`.

## 2. Checklist de precondiciones (antes de escribir el primer byte)

- [ ] Irving presente en la sesión, de principio a fin.
- [ ] Confirmado que `10.50.11.2` es la OLT de **laboratorio** (no una OLT de campo/producción).
- [ ] Sync de SmartOLT de esta OLT **pausado** (los métodos `deauthorizeOnu`/`authorizeOnu` lo piden
      explícitamente en su docblock — evita que un sync concurrente pise el estado a medio ciclo).
- [ ] Confirmado que el ONT `HWTCFEFCC9A2` sigue físicamente conectado y es el mismo de W3 (no lo
      movieron/retiraron desde junio).

## 3. Paso 0 — Backup de lectura (solo `display`, dry_run=true, cero riesgo)

Antes de tocar nada, capturar el estado actual del ONT — igual que hizo W3 Fase 1. El driver ya
expone lectura de descripción/señal (`getOnuDetails`), pero **no** expone line-profile-id /
srv-profile-id / VLANs del service-port (`getOnuDetails()` los deja `null` —
`HuaweiDriver.php:242-243` —, es un hueco conocido, no un bug de esta sesión). Para el ciclo
`deauthorizeOnu → authorizeOnu` hace falta ese dato para re-provisionar **idéntico**, así que se
lee a mano vía la sesión Telnet ya abierta por el driver (o telnet manual) antes de continuar:

```
display ont info 2 0                    # (dentro de interface gpon 0/3) — SN, descripción, estado
display ont-lineprofile-id gpon 0/3 ont 0     # line-profile-id vigente (ajustar sintaxis exacta a lo que acepte esta VRP)
display service-port port 0/3/2 ont 0         # srv-profile-id / svlan / user_vlan / cvlan / gemport vigentes
```

Guardar la salida completa en un archivo nuevo (mismo patrón que W3):
`docs/multiolt-w3-backups/w4_fase_a_backup_<fecha>.txt`. Sin este backup, **no continuar** — es
el único plan de rollback real para el ciclo de authorize/deauthorize.

## 4. Paso 1 (menos destructivo) — `setOnuEnabled` (activar/desactivar)

Suspende/reactiva la sesión OMCI sin borrar la config del ONT — el más seguro de los tres, mismo
nivel de riesgo que el `descOnt` ya validado en W3.

```php
// php artisan tinker
$cfg = config('services.huawei_olt');
$session = new \App\Services\OltDriver\Huawei\TelnetSession($cfg);
$transport = new \App\Services\OltDriver\Huawei\HuaweiTransport($cfg, $session);

// 1a) dry-run primero, SIEMPRE
$driver = new \App\Services\OltDriver\Huawei\HuaweiDriver($transport, array_merge($cfg, ['dry_run' => true, 'olt_id' => 'ma5800-x7']));
$driver->setOnuEnabled('1:0/3/2:0', false);   // revisar $result['commands'] antes de seguir

// 1b) real, solo con Irving mirando la pantalla
$driverLive = new \App\Services\OltDriver\Huawei\HuaweiDriver($transport, array_merge($cfg, ['dry_run' => false, 'olt_id' => 'ma5800-x7']));
$result = $driverLive->setOnuEnabled('1:0/3/2:0', false);
// verificar $result['success'] + leer estado con getOnuDetails() o `display ont info 2 0`
// re-activar de inmediato:
$driverLive->setOnuEnabled('1:0/3/2:0', true);
```

**Criterio de aceptación de este paso:** `display ont info 2 0` muestra el ONT `down`/`offline`
tras `false` y vuelve a `up`/`online` (mismo estado que antes) tras `true`, sin errores en
`$result['message']`.

## 5. Paso 2 (más destructivo, solo si el Paso 1 salió limpio) — ciclo `deauthorizeOnu` → `authorizeOnu`

`deauthorizeOnu` borra el registro del ONT en la OLT; el docblock del método ya lo advierte:
"Usar en el ciclo: deauthorizeOnu() → authorizeOnu() para re-provisionar" — **nunca dejar el ONT
desautorizado entre sesiones**, el ciclo se cierra en la misma sesión.

```php
// 2a) dry-run del deauthorize
$driver->deauthorizeOnu('1:0/3/2:0');   // revisar comandos generados

// 2b) real
$resultDel = $driverLive->deauthorizeOnu('1:0/3/2:0');
// verificar $resultDel['success'] === true

// 2c) re-autorizar YA, con los valores EXACTOS leídos en el Paso 0
$dataAuth = [
    'sn'              => 'HWTCFEFCC9A2',
    'frame'           => 0, 'slot' => 3, 'port' => 2, 'ont_id' => 0,
    'line_profile_id' => /* del backup Paso 0 */,
    'srv_profile_id'  => /* del backup Paso 0 */,
    'desc'            => /* descripción vigente, la que dejó W3 o la actual */,
    'svlan'           => /* del backup Paso 0 */,
    'user_vlan'       => /* del backup Paso 0 */,
    'cvlan'           => /* del backup Paso 0, o null si single-tag */,
    'gemport'         => /* del backup Paso 0, default 1 */,
];

// dry-run primero
$driver->authorizeOnu($dataAuth);

// real
$resultAuth = $driverLive->authorizeOnu($dataAuth);
```

⚠️ **Punto marcado `@VERIFY` en el propio código** (`HuaweiDriver.php:827-829`): tras el `return`
de `addOnt()` el transport queda en user-view, pero `addServicePort()` es un comando de
config-view. **Validar en esta sesión** si el VRP de este MA5800-X7 acepta `service-port` en
user-view o si `authorizeOnu()` necesita un `enterConfigView()` explícito antes del segundo bloque
de pasos. Si falla ahí, el ONT queda autorizado (`ont add`) pero sin service-port — visible y
recuperable (repetir solo el bloque de `addServicePort` a mano), no se pierde el ONT.

**Criterio de aceptación de este paso:** `display ont info 2 0` muestra el ONT `HWTCFEFCC9A2`
`up`/`online` de nuevo, con la misma descripción; `display service-port port 0/3/2 ont 0` muestra
el mismo `svlan`/`user_vlan`/`gemport` que el backup del Paso 0; señal óptica sin degradación
(comparar contra el ground truth ya documentado: Rx ONU ≈ -8.54 dBm, `HuaweiDriver.php:263`).

## 6. Si algo sale mal a medias

- `setOnuEnabled` fallido a medio camino: reintentar `setOnuEnabled(..., true)` — es idempotente,
  no requiere el backup del Paso 0.
- `deauthorizeOnu` exitoso pero `authorizeOnu` falla: el ONT queda **desautorizado** — no cerrar la
  sesión hasta reintentar `authorizeOnu` con los datos del backup y confirmar que vuelve a
  `online`. Es el único estado que deja el ONT de laboratorio inutilizable si se abandona.
- Cualquier duda de si el comando en curso puede afectar a otro puerto/ONU de la misma OLT:
  detener y no improvisar comandos VRP fuera de los que genera `HuaweiCommandBuilder` — son los
  únicos ya verificados contra este firmware (V100R018).

## 7. Después de validar Fase A

- **No** expandir `ReadOnlyGuard::WRITE_ALLOW_LIST` en este runbook — esa es una decisión aparte,
  posterior a tener los tres métodos validados y, si aplica, sus fixes (p. ej. el `@VERIFY` de
  config-view) ya commiteados con sus tests.
- Registrar en la Hoja de Ruta (`circuito:reportar --tipo=decision` o directamente con Irving) qué
  se validó, qué falló, y qué cambió en el código como resultado — antes de considerar Fase A
  cerrada.
- Fase B (`setOnuSpeedProfile`, service-ports/VLANs de plan contratado) sigue bloqueada hasta que
  esta Fase A esté validada Y exista una decisión de Irving para empezar a implementarla (hoy ni
  siquiera tiene código, solo el stub que lanza `WriteNotEnabledException`).
