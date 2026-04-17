<?php

namespace App\Observers;

use App\Models\BillingConfiguration;
use App\Models\TypeBilling;
use App\Services\ClientService\BillingPaymentDateService;

class BillingConfigurationObserver
{
    /**
     * Handle the BillingConfiguration "updating" event.
     *
     * @param  \App\Models\BillingConfiguration  $billingConfiguration
     * @return void
     */

    public function updating(BillingConfiguration $billingConfiguration)
    {
        $client = $billingConfiguration->client;
        $actualBillingDate = $billingConfiguration->billing_date;
        if ($billingConfiguration->isDirty('billing_expiration') || $billingConfiguration->isDirty('billing_date')) {
            if ($client->client_main_information->type_of_billing_id == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT) {
                $billingPaymentDateService = new BillingPaymentDateService();
                $billingPaymentDateService->setNewPaymentDateWhenBillingDateChange($client, $actualBillingDate);
            }
        }
    }
}
