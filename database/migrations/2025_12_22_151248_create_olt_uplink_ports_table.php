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
        Schema::create('olt_uplink_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('mode')->nullable();
            $table->string('admin_status')->nullable();
            $table->string('status')->nullable();
            $table->string('vlan_tag')->nullable();
            $table->string('negotiation_auto')->nullable();
            $table->string('mtu')->nullable();
            $table->string('wavelength')->nullable();
            $table->string('temperature')->nullable();
            $table->string('pvid')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['olt_id', 'name'], 'unique_olt_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_uplink_ports');
    }
};
