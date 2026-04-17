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
        Schema::create('olt_pon_ports', function (Blueprint $table) {
            $table->id();
            $table->integer('board');
            $table->integer('pon_port');
            $table->string('pon_type')->nullable();
            $table->string('admin_status')->nullable();
            $table->string('operational_status')->nullable();
            $table->string('onus_count')->nullable();
            $table->string('online_onus_count')->nullable();
            $table->string('average_signal')->nullable();
            $table->string('min_range')->nullable();
            $table->string('max_range')->nullable();
            $table->string('tx_power')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['olt_id', 'board', 'pon_port'], 'unique_pon_port_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_pon_ports');
    }
};
