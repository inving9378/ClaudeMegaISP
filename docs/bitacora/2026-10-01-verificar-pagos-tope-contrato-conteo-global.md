## 2026-10-01 11:00 — recurrent_tope_duracion_contrato: endurecido contra el conteo global de jobs

### Reporte

Otra terminal reportó, tras la emisión de V1.48: el chequeo `recurrent_tope_duracion_contrato`
seguía fallando en su sesión — "el cliente 167, aún dentro de su contrato, no se cobró sin
saldo". Aclaró explícitamente que es del fix de política de Irving del 18-sep (tope de duración
de contrato), no de ninguno de los 5 fixes de hoy, y que no había investigado si era un cobro
legítimo bloqueado o un problema del cliente de prueba.

### Investigación

No se reprodujo el fallo en esta sesión: `pagos:verificar-recurrentes --dry-run` corrido **5
veces consecutivas**, las 5 en verde. Se verificó el estado real del cliente #167 en este
momento: **1 servicio activo** (internet) y **4 de 6 meses restantes** de su contrato — dentro
de contrato, con servicios que cobrar. La lógica de negocio del tope de contrato
(`ClientBillingService::clientHasReachedContractCap()`, que reusa
`ClientService::getDataPendingPayments()`) **no se tocó** — se confirmó que sigue intacta.

Se investigó el propio chequeo en busca de fragilidad de diseño, y se encontró una real:

```php
$jobsAntes = DB::table('jobs')->count();           // ← TODA la tabla, sin filtrar
$billingService->billingServicesByClient($dentro, ...);
$jobsTrasDentro = DB::table('jobs')->count();       // ← compara contra el total global
```

Este chequeo medía "¿se cobró?" contando **toda** la tabla `jobs` — compartida por tickets,
WhatsApp, cobranza, deploys y cualquier otra cosa del sistema — en vez de algo específico al
cliente de prueba. Confirmado que hay **3 workers de cola reales** corriendo en este entorno
(`cobranza,referrals,database,default` + `deploy`), procesando/insertando filas continuamente.

Además, el fix de hoy a `ClientBillingService::actionBilling()` (`dispatchSync()` en vez de
`dispatch()`, ver
[cargo-y-fecha](2026-10-01-pagos-recurrentes-cargo-y-fecha.md)) hace que
`RectifyBalanceAndCreateTransaction` **ya no pase por la cola** en el caso exitoso — corre en el
mismo proceso. Esto redujo la señal que el conteo de `jobs` esperaba ver (antes contribuían tanto
`RectifyBalanceAndCreateTransaction` como `ClientServiceChargedJob`; ahora solo el segundo),
haciendo el margen de detección más estrecho y, por tanto, más vulnerable a cualquier ruido
externo en esa tabla compartida.

**Nota técnica verificada:** bajo `REPEATABLE-READ` (confirmado el nivel de aislamiento real de
esta conexión MySQL), dos lecturas dentro de la misma transacción deberían ver el mismo snapshot
consistente, así que la condición de carrera clásica no debería explicar un fallo estrictamente
*dentro* de una sola transacción de prueba. No se pudo confirmar con certeza el mecanismo exacto
del incidente puntual reportado por la otra terminal — pudo ser una condición de datos real del
cliente 167 en ese momento exacto (BD de dev compartida entre varias terminales en paralelo), no
necesariamente el conteo de `jobs` en sí. Pero el diseño del chequeo era innecesariamente frágil
de todos modos, y merece el mismo endurecimiento que los 5 chequeos agregados hoy.

### Fix

Reemplazado el conteo de `jobs` por un conteo directo de `transactions` (categoría `Servicio`,
tipo `debit`) **por cliente específico** — mismo patrón ya usado en los 5 chequeos de hoy:

```php
$cargosDentroAntes = DB::table('transactions')->where('client_id', $dentro->id)
    ->where('category', 'Servicio')->where('type', 'debit')->count();
// ... cobro ...
$cargosDentroDespues = DB::table('transactions')->where('client_id', $dentro->id)
    ->where('category', 'Servicio')->where('type', 'debit')->count();
$seCobroDentro = $cargosDentroDespues > $cargosDentroAntes;
```

Aislado a la transacción de prueba (se revierte), sin depender de ninguna tabla global
compartida ni del estado de la cola.

### Verificación

- `pagos:verificar-recurrentes --dry-run` → **5/5 corridas consecutivas en verde**.
- Batería completa de 11 casos de los 5 fixes de hoy → **0 fallas** (este cambio solo toca el
  chequeo de verificación, ningún código de negocio).

### Commit

`dbf3eebd` en la rama `fix/verificar-pagos-tope-contrato-conteo-global` (desde `main`, que ya
incluye los 5 fixes anteriores + V1.48 publicada). Archivo:
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.

### Nota de alcance

Este cambio solo toca el **chequeo automatizado**, no ninguna lógica de cobro real. Nivel A
(aditivo, reversible, no toca dinero/permisos/auth/producción — solo endurece una herramienta de
verificación interna).
