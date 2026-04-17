<?php

use App\Models\MapDevicePortConnection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new MapDevicePortConnection())->removeOrphansConnections();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
