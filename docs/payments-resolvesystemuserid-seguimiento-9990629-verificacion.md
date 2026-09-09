# Item #9990641 — Cerrar #9990629 (seguimiento de #272): `resolveSystemUserId` ya usa MEGAISP (RESUELTO — sin código adicional)

## Pedido del item

Sub-item de seguimiento de #9990629, que a su vez era seguimiento del item #272. La pregunta
sin resolver que generó la cadena era la propia escalación boilerplate original de #272
("toca frontera de dinero, ¿cómo procedemos?"), sin ninguna duda técnica real detrás. El item
pedía: (1) confirmar que el commit de #272 sigue en `main`, (2) documentar el estado, (3)
opcionalmente anotar en `CLAUDE.md`, (4) cerrar — **explícitamente sin rama de código de
negocio**, solo el commit de documentación.

Irving aprobó la Opción 1 de la pregunta estructurada (`opcion_elegida=3dd6657bbadf7592`):
cerrar con una nota breve documentando que `resolveSystemUserId` ya resuelve al usuario
MEGAISP, sin código adicional.

## Verificación

**1. El commit de #272 sigue en `main`:**

```
$ git log --oneline main | grep -i 272
ed9d4e8d Integra circuito #272 (circuito/item-272-payments-resolvesystemuserid-busca-el-r) a main
...
$ git merge-base --is-ancestor ed9d4e8d0906a6a6a2a99a875e9c440d8ccb7f56 main && echo "YES ancestor of main"
YES ancestor of main
```

**2. El código real ya usa `User::systemBot()` en los dos puntos donde antes se buscaba el rol
`SUPER_ADMIN` inexistente:**

`app/Modules/Addons/Payments/Services/PaymentApplicationService.php:280-286`
```php
private function resolveSystemUserId(): int
{
    if ($this->cachedSystemUserId !== null) {
        return $this->cachedSystemUserId;
    }
    return $this->cachedSystemUserId = User::systemBot()?->id ?? 1;
}
```

`app/Modules/Addons/Payments/Services/Conciliation/PaymentFromSessionService.php:122`
```php
$megaisp = User::systemBot()?->id ?? 1;
```

Mismo patrón en ambos: resuelve el usuario de sistema MEGAISP y solo cae a `id 1` (Admin) como
último recurso si `systemBot()` no existiera en la BD (defensivo, no el camino real).

**3. Cero referencias vivas a un rol `SUPER_ADMIN` en el flujo de pagos:**

```
$ grep -rn "SUPER_ADMIN" app/Modules/Addons/Payments/
app/Modules/Addons/Payments/migrations/2026_05_23_150500_add_permissions_payments.php:28
app/Modules/Addons/Payments/migrations/2026_06_30_140500_add_permissions_reconciliation.php:29
app/Modules/Addons/Payments/Services/PaymentApplicationService.php:274  (comentario histórico)
```

Las 2 migraciones usan `ComunConstantsController::SUPER_ADMIN_ROLE` para asignar **permisos**
(no para resolver el usuario de sistema de un pago) — no relacionadas al bug original de #272.
El único comentario restante en `PaymentApplicationService.php:274` es histórico, documentando
por qué se dejó de usar un rol y se pasó a `User::systemBot()`.

## Conclusión

El fix de #272 (atribuir los pagos automáticos SPEI/OpenPay/conciliación a MEGAISP en vez de
caer al fallback `id 1` = "Admin") está completo y en `main` desde el commit `ed9d4e8d`. La
pregunta sin resolver que generó #9990629 y luego #9990641 era la escalación boilerplate del
propio #272 ("¿cómo procedemos con la frontera de dinero?"), ya contestada por el código
mergeado + la aprobación de Irving. **Sin cambio de código** en esta vuelta — solo verificación
y cierre documental de la cadena de seguimiento #272 → #9990629 → #9990641.

Para comprobarlo en vivo: en `/finanzas/pagos`, un pago aplicado automáticamente por SPEI u
OpenPay muestra "MEGAISP" en la columna "Aplicado por" (en vez de "Admin").
