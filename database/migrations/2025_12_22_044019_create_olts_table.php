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
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->integer('smartolt_id')->unique();
            $table->string('name');
            $table->string('olt_hardware_version')->nullable();
            $table->string('ip')->nullable();
            $table->string('snmp_port')->nullable();
            $table->string('telnet_port')->nullable();
            $table->string('env_temp')->nullable();
            $table->string('uptime')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
