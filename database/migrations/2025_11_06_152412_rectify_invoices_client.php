<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\LogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $clients = Client::whereHas('invoices', function ($query) {
            $query->where('period', '2025-11');
        }, '>=', 2)
            ->with(['invoices' => function ($query) {
                $query->where('period', '2025-11')
                    ->orderByDesc('created_at') // primero las más recientes
                    ->orderByDesc('id');        // desempate si tienen misma fecha
            }])
            ->get();


        $logService = new LogService();

        foreach ($clients as $client) {
            $invoices = $client->invoices;
            $toDelete = $invoices->first();
            if ($toDelete->status === Invoice::STATUS_DRAFT) {
                $item = InvoiceItem::where('invoice_id', $toDelete->id)->get();
                foreach ($item as $item) {
                    $item->delete();
                }
                $toDelete->delete();
                $logService->log(
                    $client,
                    sprintf(
                        'Factura %d eliminada por ser duplicada en el periodo 2025-11 el día %s por el sistema',
                        $toDelete->id,
                        $toDelete->created_at->format('Y-m-d H:i:s')
                    )
                );
                Log::info(
                    sprintf(
                        'Factura %d eliminada por ser duplicada en el periodo 2025-11 el día %s por el sistema',
                        $toDelete->id,
                        $toDelete->created_at->format('Y-m-d H:i:s')
                    )
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
