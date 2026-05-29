<?php

namespace App\Listeners\Referrals;

use App\Events\InvoicePaid;
use App\Jobs\Referrals\ProcessReferralCommissions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleInvoicePaid implements ShouldQueue
{
    public string $queue   = 'referrals';
    public int    $tries   = 3;
    public array  $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function handle(InvoicePaid $event): void
    {
        ProcessReferralCommissions::dispatch($event->invoice)
            ->onQueue('referrals');
    }

    public function failed(InvoicePaid $event, \Throwable $exception): void
    {
        // El pago ya fue registrado — esto solo afecta al cálculo de comisiones.
        Log::critical('HandleInvoicePaid: falló tras 3 retries', [
            'invoice_id' => $event->invoice->id,
            'client_id'  => $event->invoice->client_id,
            'error'      => $exception->getMessage(),
        ]);
    }
}
