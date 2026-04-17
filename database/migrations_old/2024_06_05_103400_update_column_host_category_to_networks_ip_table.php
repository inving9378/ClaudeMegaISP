<?php

use App\Models\Network;
use App\Models\NetworkIp;
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
        $networksIp = NetworkIp::all();
        foreach ($networksIp as $net) {
            if ($net->used == 1) {
                $net->update([
                    'host_category' => 'Customer'
                ]);
            } else {
                $net->update([
                    'host_category' => 'Ninguno'
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $networksIp = NetworkIp::all();
        foreach ($networksIp as $net) {
            $net->update([
                'host_category' => 'Ninguno'
            ]);
        }
    }
};
