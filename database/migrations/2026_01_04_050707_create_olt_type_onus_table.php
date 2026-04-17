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
        Schema::create('olt_type_onus', function (Blueprint $table) {
            $table->id();
            $table->integer('smartolt_id')->unique();
            $table->string('name')->unique();
            $table->string('pon_type');
            $table->string('capability');
            $table->integer('ethernet_ports');
            $table->integer('wifi_ports');
            $table->integer('voip_ports');
            $table->integer('catv');
            $table->integer('allow_custom_profiles');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_type_onus');
    }
};
