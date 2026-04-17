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
        Schema::create('map_olts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Olt');
            $table->smallInteger('cards')->nullable()->default(0);
            $table->smallInteger('ports_x_card')->nullable()->default(0);
            $table->morphs('rack');
            $table->timestamps();
        });

        Schema::table('map_ports', function (Blueprint $table) {
            $table->smallInteger('card')->after('transfer_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_olts');
        Schema::table('map_ports', function (Blueprint $table) {
            $table->dropColumn('card');
        });
    }
};
