<?php

namespace App\Events;

use App\Models\ClientInvoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClientInvoice $invoice) {}
}
