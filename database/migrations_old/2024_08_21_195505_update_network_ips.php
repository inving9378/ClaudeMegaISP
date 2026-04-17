<?php

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
        $networkIps = NetworkIp::where('used', 1)->where('client_id', '!=', null)->where('host_category', '!=', 'Customer')->get();
        foreach ($networkIps as $networkIp) {
            $networkIp->update([
                'host_category' => 'Customer'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
