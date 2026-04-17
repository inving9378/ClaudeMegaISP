<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicatedGroups = DB::table('invoices')
            ->select('client_id', 'period', DB::raw('COUNT(*) as total'))
            ->whereNotNull('period')
            ->whereNull('deleted_at')
            ->groupBy('client_id', 'period')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicatedGroups as $group) {
            $invoices = Invoice::where('client_id', $group->client_id)
                ->whereHas('items')
                ->whereHas('client')
                ->with('client')
                ->with('items')
                ->where('period', $group->period)
                ->whereNull('deleted_at')
                ->orderBy('id', 'desc')
                ->get();

            $areEqual = true;
            foreach ($invoices as $invoice) {
                if (
                    $invoice->payment_id == $invoices[0]->payment_id
                    && $invoice->transaction_id == $invoices[0]->transaction_id
                    && $invoice->payment_date == $invoices[0]->payment_date
                    && $invoice->pending_balance == $invoices[0]->pending_balance
                    && $invoice->status == $invoices[0]->status
                ) {
                    $areEqual = true;
                } else {
                    $areEqual = false;
                    break;
                }
            }
            if ($areEqual) {
                //si son iguales, eliminar las facturas duplicadas
                foreach ($invoices as $invoice) {
                    if ($invoice->id != $invoices[0]->id) {
                        $invoiceToDelete = $invoices[0];
                        $invoiceItems = $invoiceToDelete->items;
                        foreach ($invoiceItems as $invoiceItem) {
                            $invoiceItem->delete();
                        }
                        $invoiceToDelete->delete();
                        Log::info('Factura duplicada eliminada: ' . $invoice->id . ' para el cliente: ' . $invoice->client_id . ' a traves de la migracion del dia ' . date('Y-m-d'));
                    }
                }
            }
        }


        Model::withoutEvents(function () {

            $payment = Payment::find(120970);
            $invoice = Invoice::find(76571);
            $transaction1 = Transaction::find(260287);
            $transaction2 = Transaction::find(260286);

            InvoiceItem::where('invoice_id', $invoice->id)->delete();

            $invoice?->delete();
            $transaction1?->delete();
            $transaction2?->delete();
            $payment?->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
