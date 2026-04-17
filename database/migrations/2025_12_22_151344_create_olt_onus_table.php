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
        Schema::create('olt_onus', function (Blueprint $table) {
            $table->id();
            $table->string('sn')->unique();
            $table->integer('board');
            $table->integer('port');
            $table->string('administrative_status')->nullable();
            $table->string('address')->nullable();
            $table->string('mode')->nullable();
            $table->string('name')->nullable();
            $table->string('onu')->nullable();
            $table->string('onu_type_name')->nullable();
            $table->string('pon_type')->nullable();
            $table->string('signal')->nullable();
            $table->string('status')->nullable();
            $table->string('zone_name')->nullable();
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
        Schema::dropIfExists('olt_onus');
    }
};
