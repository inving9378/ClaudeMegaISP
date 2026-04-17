<?php

namespace App\Console\Commands\Scripts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateIsPaymentTransactionClientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-is-payment-transaction-clients-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Todas las transacciones que sean tipo credito y que no sean pago costo de instalacion se deben marcar como is_payment = true';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            // Variable para almacenar todos los IDs actualizados
            $allUpdatedIds = [];

            // Procesar los registros en lotes de 5,000
            DB::table('transactions')
                ->where('is_payment', false)
                ->where('type', 'credit')
                ->chunkById(5000, function ($transactions) use (&$allUpdatedIds) {
                    // Obtener los IDs del lote actual
                    $ids = $transactions->pluck('id')->toArray();

                    // Ejecutar la actualización para el lote actual
                    DB::table('transactions')
                        ->whereIn('id', $ids)
                        ->update([
                            'is_payment' => true,
                            'category' => 'Pago'
                        ]);

                    // Agregar los IDs actualizados al array general
                    $allUpdatedIds = array_merge($allUpdatedIds, $ids);

                    // Mostrar el progreso
                    $this->info("Actualizado lote de " . count($ids) . " registros.");
                });

            // Mostrar la cantidad total de registros actualizados
            $totalCount = count($allUpdatedIds);
            $this->info("Se han actualizado {$totalCount} registros en total.");

            // Guardar los IDs en un archivo
            $this->storeUpdatedIds($allUpdatedIds);
        });
    }

    /**
     * Almacena los IDs actualizados en un archivo.
     *
     * @param array $ids
     */
    protected function storeUpdatedIds(array $ids)
    {
        // Convertir los IDs a una cadena separada por comas
        $idsString = implode(',', $ids);

        // Nombre del archivo
        $filename = 'updated_ids_' . now()->format('Y-m-d_H-i-s') . '.txt';

        // Guardar los IDs en un archivo en la carpeta "storage/app"
        Storage::put($filename, $idsString);

        // Mostrar la ubicación del archivo
        $this->info("Los IDs de los registros actualizados se han guardado en: storage/app/{$filename}");
    }
}