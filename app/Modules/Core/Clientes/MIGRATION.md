# Migración pendiente — Core/Clientes

⚠️ Este es el módulo más invasivo. Tocar con cuidado y por partes.

## Controllers
- `app/Http/Controllers/Module/Client/*`
- `app/Http/Controllers/ClientUserController.php`
- `app/Http/Controllers/SettingDebtPaymentClientCustomController.php`
- `app/Http/Controllers/PaymentPromiseController.php`
→ `Controllers/`

## Modelos
- `app/Models/Client.php`
- `app/Models/ClientMainInformation.php`
- `app/Models/ClientAdditionalInformation.php`
- `app/Models/ClientInternetService.php`
- `app/Models/ClientVozService.php`
- `app/Models/ClientCustomService.php`
- `app/Models/ClientBundleService.php`
- `app/Models/ClientInvoice.php` / `ClientInvoiceService.php`
- `app/Models/ClientPaymentService.php` / `ClientPaymentMetadata.php` / `ClientPaymentPromise.php`
- `app/Models/ClientGracePeriod.php`
- `app/Models/ClientLog.php`
- `app/Models/ClientUser.php`
- `app/Models/DocumentClient.php`
→ `Models/`

## Repositorios
- Cualquier `*Repository.php` con prefijo `Client*` de `app/Http/Repository/` → `Repositories/`

## Servicios
- `app/Services/ClientService/*`
- `app/Services/ClientMainInformationService.php`
→ `Services/`

## Traits
- `app/Http/Traits/Models/Client/*` → seguir en `app/Http/Traits/` pero importar
  desde la nueva ubicación del modelo.

## Rutas
- Bloque `Route::group(['prefix' => 'cliente', ...])` y derivados en `routes/web.php` → `routes.php`

## Vistas
- `resources/views/meganet/module/client/*` → `views/`

## Vue
- Permanece en `resources/js/components/module/client/*` (bundle único compilado por Mix).

## Riesgo
Hay muchísimos `use App\Models\Client*` repartidos por el codebase, también en jobs,
listeners, observers y comandos de consola. Cambiar TODOS los namespaces en un mismo
commit junto con el movimiento. Probar `php artisan route:list` y abrir la app
después de migrar.
