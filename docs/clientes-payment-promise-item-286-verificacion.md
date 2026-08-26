# Item #286 — "Clientes: eliminar processPromiseOfPayment (andamiaje resource sin ruta)" (VERIFICACIÓN — premisa incorrecta)

## Premisa del item

> Método público con cuerpo vacío y NINGUNA ruta que lo use — basura de `make:controller
> --resource` en una app que es API + Vue.
>
> - `ClientPaymentController::processPromiseOfPayment`
>
> Limpieza acotada y reversible: borrar el método.

## Hallazgo

La premisa **"ninguna ruta que lo use"** es cierta en sentido literal (no hay una entrada en
`routes.php` que apunte a `processPromiseOfPayment` directamente), pero el método **sí tiene un
consumidor real**: es llamado internamente desde `ClientPaymentController::store()`, que **sí**
está ruteado (`app/Modules/Core/Clientes/routes.php:166` → `POST /crear/{id}`):

```php
// ClientPaymentController::store(), línea 60-61
if ($request->enabled_payment_promise == "true" || $client->active_promise_payment == true) {
    $this->processPromiseOfPayment($client, $request);
}
```

`active_promise_payment` **no es un campo muerto**: es una columna real de `clients`
(`fillable` en `Client.php:68`), la escribe `PromisePaymentClientService` (líneas 49/94) al
activar/cerrar una promesa de pago, y la consultan varios scopes vivos
(`ScopeClient.php:359,372,380`). Es decir, la rama `if` de la línea 60 **es alcanzable en
producción** por cualquier cliente con una promesa de pago activa que intente registrar un pago
por el flujo normal de "Crear pago" en su ficha.

El motor de auditoría (#559) detecta rutas que apuntan **directamente** a un método público, pero
no sigue las llamadas internas `$this->método()` hechas desde otro método de la misma clase que sí
está ruteado — de ahí el falso positivo.

## Por qué NO se borra

Si se borra `processPromiseOfPayment`, la próxima vez que un cliente con `active_promise_payment =
true` (o el request llegue con `enabled_payment_promise == "true"`) intente pagar por
`POST /crear/{id}`, `store()` lanzaría `Error: Call to undefined method
ClientPaymentController::processPromiseOfPayment()` — una regresión real en un flujo de dinero.

Nota aparte (no corresponde a este item, no se toca): el cuerpo actual de
`processPromiseOfPayment` es un TODO comentado — no hace nada y `store()` no retorna/commitea en
esa rama (dejaría la transacción abierta sin commit ni rollback explícito). Es un hueco funcional
preexistente de la feature de "promesa de pago" (hay un `PromisePaymentClientService` real ya
escrito y comentado ahí mismo, líneas 173-175), no basura de scaffolding. Si se quiere completar,
es un item de producto aparte (qué debe hacer exactamente el cobro con promesa de pago activa), no
una limpieza mecánica.

## Conclusión

Sin cambio de código — el método no está muerto, tiene un consumidor real alcanzable desde una
ruta viva. Se cierra el item como verificado/resuelto por premisa incorrecta.
