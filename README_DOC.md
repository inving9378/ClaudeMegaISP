Requerimientos del .env:

-   MIX_VUE_APP_CENTER_MAP_LATITUDE
-   MIX_VUE_APP_CENTER_MAP_LONGITUDE
-   MIX_VUE_APP_GOOGLEMAPS_KEY

### Agregar estados, municipios y colonias

Después de exportar las tablas en formato SQL a través de phpMyAdmin en el entorno local y copiarlas en produccion, es necesario realizar algunas modificaciones en la estructura de la tabla por ejemplo: `colonies`. Sigue los siguientes pasos:

Los archivos deben estar en esta direccion `config/state_municipalities_and_colonies/`

1. Abre el archivo SQL exportado en un editor de texto.
2. Busca la siguiente línea de código:
    ```sql
    CREATE TABLE `colonies` (
      `id` bigint(20) UNSIGNED NOT NULL,
      `name` varchar(255) DEFAULT NULL,
      `municipality_id` int(10) UNSIGNED NOT NULL,
      `created_at` timestamp NULL DEFAULT NULL,
      `updated_at` timestamp NULL DEFAULT NULL,
      `data` text DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ```
3. Reemplaza el código anterior con el siguiente:
    ```sql
    ALTER TABLE `colonies`
    MODIFY COLUMN `id` bigint(20) UNSIGNED NOT NULL,
    MODIFY COLUMN `name` varchar(255) DEFAULT NULL,
    MODIFY COLUMN `created_at` timestamp NULL DEFAULT NULL,
    MODIFY COLUMN `updated_at` timestamp NULL DEFAULT NULL;
    ```
4. Elimina la siguiente línea de código:
    ```sql
    ALTER TABLE `colonies`
    ADD PRIMARY KEY (`id`);
    ```

## Pasos para crear un módulo en Importación

1.  **Agregar el nombre del módulo en ModuleRepository:**

    -   Dirígete a `\App\Http\Repository\ModuleRepository`.
    -   Encuentra la constante `MODULES_FOR_IMPORT`.
    -   Agrega el nombre del módulo existente en la tabla `modules`. Por ejemplo: `'ClientVozService'`.

    ```php
    const MODULES_FOR_IMPORT = [
        "ClientVozService",
    ];

    ## Agregar función en el Modelo Correspondiente al Módulo "ClientVozService"

    ```

2.  **En el modelo correspondiente al módulo "ClientVozService", agrega la siguiente función::**

    ```php
    public function getRequestAndStoreMethod()
    {
        $request = new ClientVozServiceCreateRequest(); // Este es el request que está en el método store del controlador
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientVozServiceController@store'; // Ruta del controlador con su método store
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id', // Este es un campo opcional para cuando es necesario el parámetro $id en el store. Por ejemplo: (public function store(ClientVozServiceCreateRequest $request, $id))
        ];
    }

    ```

3.  **En `App\Http\Controllers\Utils\ComunConstantsController`, declarar las reglas de validación para ese módulo en la constante `RULES`:**

    ```php
    const RULES = [
        'ClientVozService' => [
            'amount' => 'required',
            'unity' => 'required',
            'start_date' => 'required',
            'pay_period' => 'required',
            'estado' => 'required',
            'password' => 'required',
            'voise_device' => 'required',
            'direction' => 'required',
            'phone' => 'required',
        ]
    ];
    ```

    -   Casi siempre coinciden con las mismas reglas del `ClientVozServiceCreateRequest`. Tener en cuenta que estos son los que se aplican, no los que existen en `ClientVozServiceCreateRequest`. Si se va a agregar un Request Personalizado usando reglas de Laravel, se hace en `App\Http\Traits\ValidationImportModuleTrait` en la función:

        ```php
        public function getRulesWithModel($model, $input)
        {
            $rules = ComunConstantsController::RULES;
            if (isset($rules[$model])) {
                if ($model == 'Client') {
                    $rules[$model]['email'] = new ValidateEmailImportClient($input);
                }
                if ($model == 'ClientInternetService') {
                    $rules[$model]['ipv4'] = new ValidateIpv4ImportPertenceAlRouter($input);
                    $rules[$model]['ipv4_pool'] = new ValidateIpv4ImportPertenceAlRouter($input);
                }
                return $rules[$model];
            } else {
                return [];
            }
        }
        ```

4.  **Cuando quieras que en el archivo Excel que se exporta, las columnas vayan en el orden deseado, agrega en `App\Http\Controllers\Utils\ComunConstantsController`:**

    ```php
    const ORDER_COLUMNS_MODULE_TO_EXPORT_EXCEL = [
        'ClientVozService' => [
            'client_id', // Esta columna se mostrará primero. Si no existe, se crea. Tener en cuenta esto.
        ],
    ];
    ```

    Esto te permitirá especificar el orden de las columnas para el módulo `ClientVozService` al exportar a Excel.

# Documentación de CheckIndexService para Optimización de Tablas

## Descripción

El `CheckIndexService` en Laravel permite verificar y agregar índices a las columnas especificadas en diversas tablas de la base de datos. Esto ayuda a mejorar el rendimiento de las consultas al asegurarse de que las columnas clave tengan índices.

## Preparación de Datos

Primero, definimos un array asociativo donde cada clave es el nombre de una tabla y cada valor es un array de nombres de columnas que queremos verificar para asegurarnos de que están indexadas.

```php
$tablesAndColumns = [
    'clients' => ['id'],
    'nomenclatures' => ['client_id', 'name'],
    'client_main_information' => ['client_id', 'address', 'estado', 'partner_id', 'location_id', 'state_id',
    ];
];
```

## Uso del servicio

```php
$service = new CheckIndexService();
$missingIndexes = $service->checkMissingIndexes($tablesAndColumns);
$indexedColumns = $service->addMissingIndexes($missingIndexes);
```

-   Parámetro: $tablesAndColumns - Array asociativo donde las claves son nombres de tablas y los valores son arrays de columnas a verificar.
-   Resultado: $missingIndexes - Devuelve un array de columnas que no tienen un índice.
-   Resultado: $indexedColumns - Devuelve un array de columnas que fueron indexadas.
