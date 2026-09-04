# Verificación de "Documentos de conductor" — item #93 (RESUELTO — ya implementado)

El item #93 pedía: "Hoy los documentos se asocian solo al vehículo (`vehicle_id`). Para
licencias de operador independientes del vehículo... agregar `driver_id` nullable a
`fleet_documents` o crear tabla separada `fleet_driver_documents`."

**El trabajo ya está hecho y mergeado a `main` desde antes de que este pase de circuito lo
tomara.** El item se quedó atorado en el flujo del roadmap por una cadena de reclamos
huérfanos (reaper) sin relación con el estado real del código — no porque faltara
implementación. Verificado en dev (2026-09-04):

## 1. Columna + FK (commit `81645745`, 2026-06-30)

Migración `2026_06_30_120000_add_driver_id_to_fleet_documents.php` — exactamente la opción
"nullable" que planteaba el item:

```php
$table->foreignId('driver_id')->nullable()->after('vehicle_id')
      ->constrained('users')->nullOnDelete();
```

`php artisan migrate:status` confirma que ya corrió en la BD de dev (batch 540). El
"conductor" se modela como `users` (igual que `fleet_assignments.user_id`) — no existe ni
se necesita una tabla `fleet_drivers` aparte.

## 2. `vehicle_id` ahora también nullable (commit `6766da1a`, #177a, 2026-08-20)

Un seguimiento posterior (#177, decisión explícita de Irving) fue más allá de lo que pedía
#93: hizo `vehicle_id` nullable también, para que un documento pueda ser **100% de
conductor** (sin vehículo), con su propio candado de tenant (`scopeForClient` en
`FleetDocument.php`: un documento sin vehículo solo es visible en el scope interno Meganet,
nunca se filtra a un `client_id` externo) y una validación a nivel modelo
(`FleetDocument::boot()`) que exige que venga `vehicle_id` **o** `driver_id` (nunca ningún
documento huérfano de ambos).

## 3. Controller y validación (`FleetDocumentController::store`)

```php
'vehicle_id' => 'nullable|required_without:driver_id|integer',
'driver_id'  => 'nullable|required_without:vehicle_id|integer|exists:users,id',
```

Ya acepta ambos casos (documento de vehículo, de conductor, o de ambos) y bloquea crear un
documento sin vehículo desde un scope de cliente externo (línea 69-74).

## 4. Catálogo de tipos ya anticipa "licencia del operador"

`useFleetFormatters.js` ya trae `operator_license: 'Licencia del operador'` con su propio
icono (`bi-person-badge`) en el enum de `document_type` — el caso de uso que motivó el item
(licencias de operador) ya tiene su lugar en el catálogo.

## Lo que NO existe (gap real, pero fuera del alcance literal de #93)

No hay una **pantalla** que permita crear/ver un documento *sin* pasar por la ficha de un
vehículo (`FleetTabDocumentos.vue` vive dentro de `FleetVehicleShow.vue` y siempre manda
`vehicle_id` en el `FormData`). La capacidad de backend para un documento 100%-de-conductor
existe y está probada (item #177), pero hoy solo es alcanzable por API directa, no desde la
UI. El item #93, tal como está escrito, pedía la columna/relación de datos — no una pantalla
nueva de "conductores" — y quedó satisfecho por el diseño ya aplicado (confirmado también
por el des-trabe técnico previo del propio item: "Agregar columna driver_id... resuelve el
diseño mecánicamente"). Construir una pantalla dedicada de conductores es una función nueva,
de alcance/diseño propio (dónde vive, qué permisos, qué lista), y se deja fuera a propósito
en vez de improvisarla en esta vuelta — si se decide hacer, es un item aparte.

## Conclusión

Sin cambio de código de aplicación en esta vuelta — el modelo de datos que pedía #93 ya
está completo, corrido en dev y en uso por el controller/validaciones. Se cierra
documentando la verificación.
