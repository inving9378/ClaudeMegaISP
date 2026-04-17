<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\InventoryItemStoreZone;
use App\Models\InventoryItemType;
use App\Models\InventoryStore;
use App\Models\StoreZone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportDataInventory extends Command
{
    protected $signature = 'app:import-data-inventory';
    protected $description = 'Import inventory data from Excel file';
    private $rowsNotProcessedCount = 0;
    private $rowsNotProcessed = [];

    // Constants for status mapping
    private const ITEM_STATUS_MAP = [
        'nuevo' => 'new',
        'new item' => 'new',
        'usado' => 'used',
        'repair' => 'repair',
        'reparación' => 'repair',
        'en reparacion' => 'repair',
        'en reparación' => 'repair',
        'warranty' => 'warranty',
        'garantía' => 'warranty',
        'garantia' => 'warranty',
        'bueno' => 'good',
        'en garantía' => 'warranty',
        'en garantia' => 'warranty',
        'broken' => 'broken',
        'rotos' => 'broken',
    ];

    private const DEFAULT_ITEM_STATUS = 'new';
    private const DEFAULT_USER_ID = 1;

    public function handle()
    {

        try {
            $this->prepareDatabase();
            $this->importExcel();
            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            $this->info('Inventory import completed successfully.');
        } catch (\Exception $e) {
            $this->error('Error during import: ' . $e->getMessage());
            Log::error('Inventory import failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return 1;
        }

        return 0;
    }
    private function prepareDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        DB::table('inventory_item_stocks')->truncate();
        DB::table('inventory_item_store_zones')->truncate();
        DB::table('inventory_item_types')->where('name', 'GRAPAS')->update([
            'type' => 'material'
        ]);
        DB::table('inventory_items')->truncate();
        DB::table('inventory_movements')->truncate();
    }

    private function importExcel(): void
    {
        $excelPath = database_path('files/inventory_import1.xlsx');

        if (!file_exists($excelPath)) {
            throw new \RuntimeException("Excel file not found at: {$excelPath}");
        }

        $spreadsheet = IOFactory::load($excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->getHeaders($sheet);
        $processedRows = 0;
        $rowNumber = 2;

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowData = $this->getRowData($row, $headers);
            $this->processRow($rowData, $rowNumber);
            $rowNumber++;
            $processedRows++;

            // Progress feedback for large files
            if ($processedRows % 100 === 0) {
                $this->info("Processed {$processedRows} rows...");
            }
        }

        $this->info("Total rows processed: {$processedRows}");
        $this->info("Rows not processed: {$this->rowsNotProcessedCount}");
        $this->info("Rows not processed: " . json_encode($this->rowsNotProcessed));
    }

    private function getHeaders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = $cell->getValue();
            }
        }
        return $headers;
    }

    private function getRowData(\PhpOffice\PhpSpreadsheet\Worksheet\Row $row, array $headers): array
    {
        $rowData = [];
        foreach ($row->getCellIterator() as $cell) {
            $rowData[] = $cell->getValue();
        }

        if (count($headers) !== count($rowData)) {
            throw new \RuntimeException('Header and row data count mismatch');
        }

        return array_combine($headers, $rowData);
    }

    private function processRow(array $rowData, int $rowNumber): void
    {
        $itemId = $this->createInventoryItem($rowData);
        if ($storeId = $this->createInitialMovementStore($itemId, $rowData)) {
            $this->createItemStock($itemId, $rowData, $storeId);
        } elseif ($userId = $this->createInitialMovementUser($itemId, $rowData)) {
            $this->createItemStockUser($itemId, $rowData, $userId);
        } else {
            $this->rowsNotProcessedCount++;
            $this->rowsNotProcessed[$rowNumber] = $rowData;
        }

    }

    private function createInventoryItem(array $rowData): int
    {
        $data = [
            'name' => $this->cleanString($rowData['name']),
            'description' => $this->cleanString($rowData['description']),
            'initial_stock' => $this->parseNumber($rowData['initial_stock']),
            'status_item' => $this->mapItemStatus($rowData['status_item']),
            'inventory_item_type_id' => $this->getOrCreateItemTypeId(
                $rowData['type_item'],
                $rowData['type_item_type'] ?? null
            ),
            'created_by' => 1,
            'high_limit' => $this->parseNumber($rowData['high_limit']),
            'middle_limit' => $this->parseNumber($rowData['middle_limit'])
        ];

        return DB::table('inventory_items')->insertGetId($data);
    }

    private function createInitialMovementStore(int $itemId, array $rowData)
    {
        $storeId = $this->getStoreId($rowData['store_id']);
        if ($storeId) {
            $zoneId = $this->getStoreZoneId($rowData['zone']);

            $data = [
                'inventory_item_id' => $itemId,
                'created_by' => self::DEFAULT_USER_ID,
                'type' => 'Entrada',
                'quantity' => $this->parseNumber($rowData['initial_stock']),
                'description' => 'Ingreso Inicial',
                'movementable_to_id' => $storeId,
                'movementable_to_type' => 'App\Models\InventoryStore',
                'movementable_from_id' => self::DEFAULT_USER_ID,
                'movementable_from_type' => 'App\Models\User',
                'status' => 'accepted',
                'created_at' => now(),
                'updated_at' => now(),
                'store_zone_id' => $zoneId,
                'is_initial' => true
            ];

            if ($zoneId) {
                $inventoryStoreZone = $this->findOrCreateInventoryStoreZone($itemId, $zoneId, $storeId);
                $inventoryStoreZone->increment('quantity', $this->parseNumber($rowData['initial_stock']));
            }

            DB::table('inventory_movements')->insert($data);
            return $storeId;
        }

        return null;
    }

    private function createInitialMovementUser(int $itemId, array $rowData)
    {
        $userId = $this->getUserIdByName($rowData['store_id']);
        if ($userId) {
            $data = [
                'inventory_item_id' => $itemId,
                'created_by' => self::DEFAULT_USER_ID,
                'type' => 'Entrada',
                'quantity' => $this->parseNumber($rowData['initial_stock']),
                'description' => 'Ingreso Inicial desde Scrip de Importacion',
                'movementable_to_id' => $userId,
                'movementable_to_type' => 'App\Models\User',
                'movementable_from_id' => self::DEFAULT_USER_ID,
                'movementable_from_type' => 'App\Models\User',
                'status' => 'accepted',
                'created_at' => now(),
                'updated_at' => now(),
                'is_initial' => true
            ];
            DB::table('inventory_movements')->insert($data);
            return $userId;
        }

        return null;
    }

    private function createItemStock(int $itemId, array $rowData, int $storeId): void
    {
        $data = [
            'inventory_item_id' => $itemId,
            'modelable_type' => 'App\Models\InventoryStore',
            'modelable_id' => $storeId,
            'current_stock' => $this->parseNumber($rowData['initial_stock'])
        ];

        DB::table('inventory_item_stocks')->insert($data);
    }
    private function createItemStockUser(int $itemId, array $rowData, int $userId): void
    {
        $data = [
            'inventory_item_id' => $itemId,
            'modelable_type' => 'App\Models\User',
            'modelable_id' => $userId,
            'current_stock' => $this->parseNumber($rowData['initial_stock'])
        ];

        DB::table('inventory_item_stocks')->insert($data);
    }

    private function cleanString(?string $value): string
    {
        return trim($value ?? '');
    }

    private function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }

        if (!is_string($value)) {
            return 0.0;
        }

        $cleaned = preg_replace('/[^0-9.,-]/', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);

        // Handle multiple decimal points
        if (substr_count($cleaned, '.') > 1) {
            $parts = explode('.', $cleaned);
            $last = array_pop($parts);
            $cleaned = implode('', $parts) . '.' . $last;
        }

        $result = (float)$cleaned;

        if (!is_numeric($result)) {
            Log::warning("Failed to parse number: {$value}");
            return 0.0;
        }

        return $result;
    }



    private function mapItemStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return self::ITEM_STATUS_MAP[$status] ?? self::DEFAULT_ITEM_STATUS;
    }

    private function getOrCreateItemTypeId(string $itemName, ?string $itemType): int
    {
        $type = $this->determineItemType($itemType);

        return InventoryItemType::firstOrCreate(
            ['name' => $this->cleanString($itemName)],
            [
                'type' => $type,
                'created_by' => self::DEFAULT_USER_ID
            ]
        )->id;
    }

    private function determineItemType(?string $itemType): string
    {
        if (!$itemType) {
            return 'tool';
        }

        return strtoupper($itemType) === "MATERIAL" ? 'material' : 'tool';
    }

    private function getStoreId(string $storeName)
    {
        $store = InventoryStore::where('name', $storeName)->first();

        if ($store) {
            return $store->id;
        }
        return null;
    }

    private function getUserIdByName(string $name)
    {
        $user = User::where('name', $name)->notClientRole()->first();

        if ($user) {
            return $user->id;
        }
        return null;
    }

    private function getStoreZoneId(string $zoneName)
    {
        // Transformar "A1" a "A-1" y "C1" a "C-1"
        $formattedZoneName = preg_replace('/([A-Z])(\d+)/', '$1-$2', $zoneName);

        $zone = StoreZone::where('name', $formattedZoneName)->first();

        if (!$zone) {
            return null;
        }

        return $zone->id;
    }


    protected function findOrCreateInventoryStoreZone($inventoryItemId, $storeZoneId, $inventoryStoreId)
    {
        return \App\Models\InventoryItemStoreZone::firstOrCreate(
            [
                'inventory_item_id' => $inventoryItemId,
                'store_zone_id' => $storeZoneId,
                'inventory_store_id' => $inventoryStoreId,
            ],
            ['quantity' => 0] // Valor inicial si se crea un nuevo registro
        );
    }
}
