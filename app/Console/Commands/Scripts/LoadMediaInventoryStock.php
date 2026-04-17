<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\InventoryItemStockRepository;
use App\Services\FileUploadService;
use App\Services\InventoryItemMediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class LoadMediaInventoryStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:load-media-inventory-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Carga imágenes para los InventoryItemStock desde archivos nombrados por ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        try {
            DB::beginTransaction();
            DB::table('inventory_item_media')->truncate();

            $this->extract();
            $this->importMediaToItemStock();

            DB::commit();
            $this->info('Inventory media import completed successfully.');
        } catch (\Exception $e) {
            // Solo hacer rollback si hay una transacción activa
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->error('Error during import: ' . $e->getMessage());
            Log::error('Inventory media import failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return 1;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return 0;
    }

    private function prepareDatabase(): void
    {
        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        DB::table('inventory_item_media')->truncate();
    }

    private function extract(): void
    {
        $zipPath = database_path('files/images.zip');
        $extractPath = database_path('files/images');

        // Crear directorio si no existe
        if (!File::exists($extractPath)) {
            File::makeDirectory($extractPath, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath)) {
            $zip->extractTo($extractPath);
            $zip->close();
            $this->info('Images extracted successfully.');
        } else {
            throw new \Exception('Failed to open the zip file.');
        }
    }

    private function importMediaToItemStock()
    {
        $inventoryItemStocks = (new InventoryItemStockRepository())->getAll();
        $inventoryMediaService = new InventoryItemMediaService();
        $fileUploadService = new FileUploadService();
        $imagePath = database_path('files/images');

        $files = File::files($imagePath);
        $processed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            // Obtener el ID del nombre del archivo (ejemplo: 10.jpg -> 10)
            $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            if (!is_numeric($filename)) {
                $this->warn("Skipping non-numeric filename: {$file->getFilename()}");
                $skipped++;
                continue;
            }

            $itemStockId = (int)$filename;

            // Verificar si existe el InventoryItemStock con este ID
            $itemStock = $inventoryItemStocks->firstWhere('id', $itemStockId);

            if (!$itemStock) {
                $this->warn("No InventoryItemStock found for ID: {$itemStockId}");
                $skipped++;
                continue;
            }

            try {

                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $file->getRealPath(),                 // Ruta absoluta al archivo
                    $file->getBasename(),                 // Nombre del archivo
                    mime_content_type($file->getPathname()),
                    null,                                 // Tamaño (null para que lo calcule automáticamente)
                    UPLOAD_ERR_OK,                        // Código de error (0 = OK)
                    false                                 // test = false (CRUCIAL)
                );

                // Forzar la validación del archivo
                $uploadedFile->isValid(); // Esto actualiza el estado interno

                $directory = 'inventory_item_stock/' . $itemStockId . '/document';
                $fullPath = storage_path('app/public/uploads/' . $directory);

                // Asegurar que el directorio existe
                if (!File::exists($fullPath)) {
                    File::makeDirectory($fullPath, 0755, true, true);
                }
                $properties = $fileUploadService->uploadImageAndReturnProperties($uploadedFile, $directory);

                if ($properties === false) {
                    throw new \Exception("Failed to upload image");
                }

                $inventoryMediaService->createMediaToItemStock($itemStockId, $properties);

                $processed++;
                $this->info("Processed image for InventoryItemStock ID: {$itemStockId}");
            } catch (\Exception $e) {
                $this->error("Error processing image for ID {$itemStockId}: " . $e->getMessage());
                Log::error("Error processing image for ID {$itemStockId}: " . $e->getMessage(), [
                    'file' => $file->getFilename(),
                    'exception' => $e
                ]);
                $skipped++;
            }
        }

        $this->info("Media import completed. Processed: {$processed}, Skipped: {$skipped}");
    }
}
