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
        Schema::create('olt_vlans', function (Blueprint $table) {
            $table->id();
            $table->integer('smartolt_id')->unique();
            $table->string('vlan')->nullable();
            $table->string('scope')->nullable();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('olt_vlans');
    }
};
