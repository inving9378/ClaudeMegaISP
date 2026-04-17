<?php

use App\Models\MapLayer;
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
        $layers = MapLayer::where('dialog', 'service_box')->doesntHave('splitters')->get();
        foreach ($layers as $l) {
            $l->createSplitter();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
