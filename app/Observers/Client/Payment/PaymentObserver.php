<?php

namespace App\Observers\Client\Payment;

use App\Models\Payment;
use App\Http\Repository\ClientRepository;
use Illuminate\Support\Facades\Queue;

const PAYMENTTABLE_TYPE = [
    'App\Models\Client' => 'App\Jobs\Client\Payment\PaymentClientJob',
];

class PaymentObserver
{
    protected $clientRepository;

    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    public function created(Payment $payment)
    {
            if (isset(PAYMENTTABLE_TYPE[$payment->paymentable_type])) {
                $job = PAYMENTTABLE_TYPE[$payment->paymentable_type];
                Queue::later(0, new $job($payment, 'created'));
        }
    }

    public function deleted(Payment $payment)
    {
        if (isset(PAYMENTTABLE_TYPE[$payment->paymentable_type])) {
            $job = PAYMENTTABLE_TYPE[$payment->paymentable_type];
            $job::dispatchAfterResponse($payment, 'deleted');
        }
    }
}
