## 2026-10-09 — Reaplicado: "Registrar pago reportado" seguía bloqueado para Mostrador (el fix de ayer nunca se mergeó)

### Contexto

Irving: "termina lo que encontraste ayer" → yo interpreté mal la primera vez (pensé que se
refería a la Fase 4 del dashboard por rol). Corrección de Irving: "me refería al error de
mostrador que no podía registrar pagos". Investigando, apareció la causa real: el fix de ayer
(commit `ee6f123a`, bitácora `2026-10-08-item-pagos-captura-mostrador-sin-ruta.md`) se hizo y se
**verificó correctamente**, pero quedó viviendo solo en la rama
`fix/payments-captura-pago-route-permission` — **nunca se mergeó a `main`**. Por eso el bug
seguía vivo hoy: confirmado con un usuario Mostrador de prueba, `/finanzas/captura-pago` seguía
rebotando en silencio al dashboard.

### Causa (idéntica a la de ayer, solo que nunca llegó a producción de dev)

`/finanzas/captura-pago` (`ManualPaymentController`) nunca se registró en
`config/route_permission.php`. El middleware `CheckRoutePermission` es un **allowlist por path**
(no distingue método HTTP — una sola entrada cubre GET y POST al mismo path): sin la entrada ahí,
nadie sin bypass de super-admin pasa, sin importar el permiso que tenga. `payments_capture_manage`
ya existía y ya estaba asignado al rol Mostrador — solo le faltaba esta conexión.

### Fix (reaplicado desde cero sobre `main` actual, no merge de la rama vieja — evita arrastrar divergencia con 24h de cambios intermedios)

```php
'payments_capture_manage' => [
    '/finanzas/captura-pago',
    '/finanzas/captura-pago/buscar-cliente',
    '/finanzas/captura-pago/{id}/comprobante',
],
```

### Verificación — esta vez de punta a punta, con un pago REAL (no solo que la pantalla abre)

1. Usuario Mostrador de prueba (creado y borrado, sin tocar cuentas reales): antes del fix,
   `/finanzas/captura-pago` rebotaba a `/` en silencio (confirmado comparando la URL final tras
   la navegación); después del fix, la pantalla carga completa y conserva la URL.
2. **Flujo completo real por navegador** (Playwright): buscó un cliente real, llenó monto/fecha/
   método/titular, dio clic en "Aplicar y registrar pago" → `POST /finanzas/captura-pago` → `200`
   → `{"ok":true,"payment_id":...,"reported_payment_id":...}` → toast "Pago registrado" visible.
   El pago quedó aplicado de verdad (fila en `payments`, fila en `transactions` con el saldo
   corrido correcto, fila en `reported_payments` en `pendiente_verificar`) — el flujo que
   realmente usan Diana/Ariana hoy funciona de punta a punta, no solo la pantalla de entrada.
3. ⚠️ **Nota propia, aprendida en esta misma verificación:** el buscador hace `LIKE` por campo
   (name/father_last_name/mother_last_name) por separado — buscar el nombre completo
   ("FRANCISCO AGUILAR") no encuentra nada porque ningún campo individual contiene las dos
   palabras juntas; hay que buscar una palabra a la vez. Y al automatizar el clic en "el primer
   resultado" de una búsqueda por nombre de pila común, el primer resultado puede ser **otro
   cliente real** con el mismo nombre — así fue como el pago de prueba terminó aplicado por error
   a un cliente real (id 335) en vez del cliente de prueba designado (id 6810). **Revertido de
   inmediato y por completo** antes de cerrar: borrada la fila de `transactions` (292306), la de
   `payments` (140903) y la de `reported_payments` (3), y restaurado `balances.amount` a su valor
   exacto previo (0, confirmado contra el `account_balance` de la transacción anterior real del
   cliente). Verificado: 21 transacciones para el cliente 335 tras la limpieza (las mismas 21 que
   tenía antes de la prueba, sin la fila de prueba). Ningún otro dato de ese cliente se tocó.
4. Config cache: confirmado que `bootstrap/cache/config.php` no existe en este entorno (no hay
   caché stale que limpiar), pero se corrió `php artisan config:clear` de todos modos antes de
   verificar, por disciplina.

### Estado

Mergeado a `main`. La rama vieja `fix/payments-captura-pago-route-permission` (con el commit
`ee6f123a`, nunca integrado) queda obsoleta — su contenido ya está reaplicado aquí.

### Lección sobre el proceso (no técnica)

Un cambio completo y verificado en una rama **no cuenta como terminado** hasta que llega a
`main` — "lo encontré y lo arreglé ayer" no es lo mismo que "ya está arriba". Vale la pena, al
cerrar cualquier sesión, confirmar explícitamente que cada rama de trabajo real quedó mergeada
(o documentar por qué sigue pendiente), no solo que el commit existe.
