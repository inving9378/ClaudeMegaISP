<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_item_store_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('store_zone_id');
            $table->unsignedBigInteger('inventory_store_id');
            $table->string('quantity');
            $table->timestamps();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('store_zone_id')->nullable();
            $table->boolean('is_initial')->default(false);
        });

        DB::table('inventory_movements')->where('description', 'Ingreso Inicial')->update(['is_initial' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_store_zones');

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('store_zone_id');
            $table->dropColumn('is_initial');
        });
    }
};
