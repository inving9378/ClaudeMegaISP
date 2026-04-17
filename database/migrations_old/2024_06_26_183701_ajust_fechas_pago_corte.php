<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $all = \App\Models\ClientMainInformation::with('client')->stateBlocked()->whereHas('client', function ($query) {
            $query->whereNotNull('fecha_corte');
        })->get();

        foreach ($all as $item) {
            $item->client->update(['fecha_corte' => null]);
        }

        $all = \App\Models\ClientMainInformation::with('client')->stateBlocked()
            ->whereHas('client', function ($query) {
                $query->whereNotNull('fecha_pago');
            })->whereHas('type_billing', function ($query) {
                $query->where('id', '!=', \App\Models\TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
            })
            ->get();

        foreach ($all as $item) {
            $item->client->update(['fecha_pago' => null]);
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
