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
        Schema::dropIfExists('olt_unconfigured_onus');
        Schema::create('olt_unconfigured_onus', function (Blueprint $table) {
            $table->id();
            $table->string('sn')->unique();
            $table->integer('board');
            $table->integer('port');
            $table->string('pon_type')->nullable();
            $table->string('onu_type_name')->nullable();
            $table->string('pon_description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_unconfigured_onus');
    }
};
