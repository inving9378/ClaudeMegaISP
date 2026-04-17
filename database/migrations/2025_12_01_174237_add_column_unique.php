<?php

use App\Models\Invoice;
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
        $grupos = Invoice::whereNotNull('period')
            ->whereNotNull('client_id')
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(function ($item) {
                return $item->client_id . '-' . $item->period;
            });

        foreach ($grupos as $key => $facturas) {
            if ($facturas->count() > 1) {

                // Todas menos la primera (la más antigua)
                $duplicadas = $facturas->slice(1);

                foreach ($duplicadas as $dup) {
                    $dup->delete();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
