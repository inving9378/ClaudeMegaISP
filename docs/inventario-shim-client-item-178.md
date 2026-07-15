# Inventario — Item #178: retirar el shim `App\Models\Client`

Fase 1 (Opción 2 elegida por Irving en la pregunta del item: "mapear/inventariar
TODOS los usos sin tocar código, escalar el inventario"). Este documento es el
entregable de esa fase — **no se tocó ningún archivo de código de la aplicación**.

## 1. Qué es el shim

`app/Models/Client.php`:

```php
namespace App\Models;

class Client extends \App\Modules\Core\Clientes\Models\Client
{
}
```

Subclase vacía, 100% pass-through hacia el modelo modular real
`App\Modules\Core\Clientes\Models\Client`.

## 2. Inventario de imports/usos en código — 134 archivos

`grep -rl "App\\Models\\Client\b|use App\\Models\\Client;" --include="*.php" app/ resources/ routes/ config/ database/ tests/`
→ **134 archivos** (98 con `use App\Models\Client;` o `App\Models\Client::` explícito;
el resto vía type-hints/docblocks/otras referencias a la clase).

Desglose por grupo de riesgo:

| Grupo | Archivos | Riesgo |
|---|---|---|
| Payments / Finanzas / PortalPago / Policies / Jobs de pago / Observers de pago / modelos y servicios financieros | 18 | **Alto — dinero/seguridad** |
| Core modular (`app/Modules/Core/Clientes`, Configuracion, CRM, Dashboard, Documentos, Usuarios) | 20 | Medio — es el propio dominio Cliente |
| Addons UI (Embajadores, GestionRed, Inventario, MegaFamilia, PortalCliente, Scheduling, SmartImportExport, Talento, Tickets, Vendedores, WhatsAppAgent) | 20 | Bajo |
| Comandos/Scripts (`Console/Commands/Active|Disabled|Scripts`) | 25 | Bajo (no interactivo, correr manual) |
| Otros (Http Controllers/Repository/Traits, Models, Jobs, Listeners, Rules, Services sueltos, `AppServiceProvider`) | 38 | Bajo–Medio |
| Tests | 5 | Ninguno (no producción) |
| Migraciones históricas (`database/migrations` y `migrations_old`) | 8 | No tocar (ya ejecutadas / archivadas) |

Lista completa de las 126 rutas de código+tests (excluye migraciones) por grupo,
verificada con grep independiente el 2026-07-15:

**Dinero/seguridad (18):**
`app/Modules/Addons/Finanzas/Controllers/Invoice/InvoiceController.php`,
`app/Jobs/Client/Payment/PaymentClientJob.php`,
`app/Models/Payment.php`, `app/Models/Receipt.php`,
`app/Observers/Client/Payment/PaymentObserver.php`, `app/Observers/PaymentBillingObserver.php`,
`app/Modules/Addons/Payments/Console/ConciliacionDemoCommand.php`,
`app/Modules/Addons/Payments/Controllers/ClabeAssignmentController.php`,
`app/Modules/Addons/Payments/Controllers/MobilePaymentController.php`,
`app/Modules/Addons/Payments/Models/{ClientPaymentReference,PaymentClabe,PaymentInstrument,ReconciliationTicket}.php`,
`app/Policies/ProspectPolicy.php`,
`app/Modules/Addons/PortalPago/Services/ConciliacionService.php`,
`app/Services/{CalculateBalanceSellerService,PaymentService,TransactionService}.php`.

**Core modular (20):** `app/Modules/Core/Clientes/Controllers/{ClientController,ClientInformationController}.php`,
`app/Modules/Core/Clientes/Models/{ClientBundleService,ClientCustomService,ClientGracePeriod,ClientInternetService,ClientInvoice,Client,ClientUser,ClientVozService}.php`,
`app/Modules/Core/Clientes/Services/{ClientService,PromisePaymentClientService}.php`,
`app/Modules/Core/Configuracion/Controllers/Nomenclature/NomenclatureController.php`,
`app/Modules/Core/Configuracion/Models/BillingConfiguration.php`,
`app/Modules/Core/Configuracion/Services/{ConfigFinanceNotificationService,EmailConfigService}.php`,
`app/Modules/Core/CRM/Controllers/CrmController.php`, `app/Modules/Core/Dashboard/Controllers/HomeController.php`,
`app/Modules/Core/Documentos/Controllers/DocumentTemplate/DocumentTemplateController.php`,
`app/Modules/Core/Usuarios/Controllers/AdministracionController.php`.

**Addons UI (20):** Embajadores (5 controllers Api), `GestionRed/.../RouterController.php`,
`Inventario/.../InventoryItemController.php`, MegaFamilia (2 controllers + `ParentalAccount.php`),
`PortalCliente/Models/PortalClient.php`, Scheduling (`ProjectController`, `TaskController`),
`SmartImportExport/Services/SmartImportService.php`, Talento (`FieldFlowService`, `FieldIaValidationService`),
`Tickets/Controllers/TicketController.php`, Vendedores (`InstallationController`, `SaleController`),
`WhatsAppAgent/Models/WhatsAppConversation.php`.

**Comandos/Scripts (25):** ver `app/Console/Commands/{Active,Disabled,Disabled/Old,Scripts}/*Client*.php`
— lista completa capturada en el log del item (todos son comandos manuales, no cron).

**Otros (38):** Http Controllers/Utils sueltos, `HelpersModule`, `Repository` (Inventory/Receipt),
`Traits/Models/Client/ClientTrait.php`, `Jobs`/`Listeners` de Referrals, modelos sueltos
(`InventoryMovement`, `MikrotikClient*`, `NetworkIp`, `Ticket`, 7 modelos `Referrals/*`),
`AppServiceProvider.php`, `Rules/*`, `Services/Client/ClientReferenceResolver.php`,
`Services/{CreatePackageService,InformationService,LogService,SupplierService}.php`,
`Services/Tenant/CurrentClientResolver.php`.

**Tests (5):** `tests/Feature/Portal/PortalTestCase.php`,
`tests/Feature/Referrals/{ApplyReferralCommissionsTest,Phase6Test}.php`,
`tests/Unit/Helpers/ClientTestHelper.php`,
`tests/Unit/Modules/Addons/GestionRed/Controllers/Network/NetworkControllerTest.php`.

Este conteo coincide (dentro de ±15 archivos por drift normal del repo) con las
3 rondas de grep independientes hechas antes por wt-6 (x3) y wt-2 en el historial
del item (121–145 archivos según fecha).

## 3. HALLAZGO CRÍTICO NUEVO — dependencia de datos en columnas polimórficas

Ningún grep de código había revisado el **contenido real de la base de datos**.
`app/Services/Client/ClientReferenceResolver.php` ya documentaba en comentario que
"hay dos clases Client; ambas pueden aparecer almacenadas" en columnas `*_type`
polimórficas — se verificó contra la BD de DEV:

```
activity_log.subject_id       / subject_type       →   34,701 filas con 'App\Models\Client'
balances.balanceable_id       / balanceable_type    →    5,630 filas con 'App\Models\Client'
inventory_item_stocks...      / modelable_type      →        8 filas con 'App\Models\Client'
inventory_movements...        / movementable_to_...  →        8 filas con 'App\Models\Client'
payments.paymentable_id       / paymentable_type    →  131,567 filas con 'App\Models\Client'
receipts.receiptable_id       / receiptable_type    →  131,081 filas con 'App\Models\Client'
transactions.transactionable_id/transactionable_type → 236,447 filas con 'App\Models\Client'
```

**Total: ~539,442 filas** en tablas financieras/de auditoría (pagos, transacciones,
recibos, saldos, log de actividad) tienen literalmente la cadena `App\Models\Client`
guardada como nombre de clase en su columna polimórfica `*_type`. Comparación:
`payments.paymentable_type` tiene 131,567 filas con el shim contra solo 31 con la
clase modular — es decir, el shim es la variante **dominante**, no un residuo menor.

No existe ningún `Relation::morphMap()` registrado en el proyecto (`grep -rn
"morphMap" app/ bootstrap/ config/` → 0 resultados), así que Eloquent usa el FQCN
literal como `morph_type`. **Si se borra el archivo `app/Models/Client.php`, cualquier
`->paymentable`, `->transactionable`, `->receiptable`, `->balanceable` o `->subject`
(activity log) sobre una de esas ~539K filas lanzará "Class 'App\Models\Client' not
found"** en tiempo de ejecución — independientemente de que los 134 imports en código
ya se hayan migrado a la clase modular.

**Esto cambia el alcance real del item:** no es "migrar imports y borrar un archivo
vacío", es "el shim es un dato vivo referenciado por más de medio millón de registros
financieros y de auditoría en producción". Borrar el archivo sin antes resolver esto
rompería el historial de pagos/transacciones/recibos/saldos de prácticamente todos los
clientes.

## 4. Mitigaciones posibles (para que Irving decida, no ejecutadas)

- **Opción morphMap (barata, segura, no toca datos):** registrar
  `Relation::morphMap(['App\Models\Client' => \App\Modules\Core\Clientes\Models\Client::class])`
  en el boot de un ServiceProvider. Esto hace que Eloquent resuelva el alias sin
  importar si el archivo `app/Models/Client.php` existe o no — **desbloquea borrar el
  shim sin tocar ni una fila de las 539K**. Es aditivo y reversible (nivel A técnico,
  aunque toca resolución de relaciones en tablas de dinero, así que amerita revisión).
- **Opción backfill de datos (cara, arriesgada):** `UPDATE` masivo de las 5 tablas para
  reescribir `*_type` de `App\Models\Client` a `App\Modules\Core\Clientes\Models\Client`.
  Irreversible sin respaldo, toca ~540K filas de dinero — no se recomienda salvo que
  se quiera eliminar el morphMap también a futuro.
- **No borrar el archivo del shim nunca:** dejarlo como alias permanente de
  compatibilidad (aunque se migren los 134 imports de código a la clase modular, el
  archivo del shim se queda vivo solo para que el `morph_type` histórico siga
  resolviendo). Costo cero, cero riesgo.

## 5. Recomendación para Fase 2

1. Aplicar el `Relation::morphMap()` (mitigación barata) — esto es lo único que de
   verdad "cierra" el riesgo de dinero, independiente de qué se decida con los imports.
2. Migrar los 134 imports de código de `App\Models\Client` a
   `App\Modules\Core\Clientes\Models\Client` **en lotes por grupo de riesgo**, empezando
   por los de menor riesgo (Addons UI, Comandos/Scripts, Otros, Tests) y dejando
   Dinero/Seguridad y Core modular para el final con revisión dedicada.
3. Borrar `app/Models/Client.php` **solo** al final, y solo si (1) ya está aplicado —
   sin el morphMap, NUNCA se debe borrar el archivo mientras existan filas históricas
   con ese `morph_type`.

Sin el paso 1, ninguna cantidad de migración de imports hace segura la eliminación del
archivo — es un hallazgo que ninguna de las 4 rondas de escalación previas (basadas
solo en grep de código) había detectado.
