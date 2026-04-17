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
        Schema::create('olt_cards', function (Blueprint $table) {
            $table->id();
            $table->integer('slot');
            $table->string('type')->nullable();
            $table->string('real_type')->nullable();
            $table->string('ports')->nullable();
            $table->string('software_version')->nullable();
            $table->string('role')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamp('info_updated')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['olt_id', 'slot'], 'unique_olt_slot_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_cards');
    }
};
