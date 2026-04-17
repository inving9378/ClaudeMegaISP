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
        Schema::dropIfExists('map_clients');
        Schema::dropIfExists('map_poles');
        Schema::dropIfExists('map_service_boxs');
        Schema::dropIfExists('map_junction_boxs');
        Schema::dropIfExists('map_packs');
        Schema::dropIfExists('map_cupboards');
        Schema::dropIfExists('map_sources');
        Schema::dropIfExists('map_buildings');
        Schema::dropIfExists('map_notes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
