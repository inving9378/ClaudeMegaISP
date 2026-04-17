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
        Schema::create('map_switch', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Switch');
            $table->smallInteger('ports_eth')->nullable()->default(0);
            $table->smallInteger('ports_1_gb')->nullable()->default(0);
            $table->smallInteger('ports_10_gb')->nullable()->default(0);
            $table->smallInteger('ports_100_gb')->nullable()->default(0);
            $table->morphs('rack');
            $table->timestamps();
        });

        Schema::table('map_ports', function (Blueprint $table) {
            $table->smallInteger('transfer')->after('connected')->nullable();
            $table->string('transfer_type')->after('transfer')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_switch');
        Schema::table('map_ports', function (Blueprint $table) {
            $table->dropColumn(['transfer', 'transfer_type']);
        });
    }
};
