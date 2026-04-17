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
        Schema::create('commissions_details', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('bonus')->nullable();
            $table->unsignedBigInteger('commission_id');
            $table->foreign('commission_id')->references('id')->on('commissions');
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->foreign('bundle_id')->references('id')->on('client_bundle_services');
            $table->unsignedBigInteger('prospect_id')->nullable();
            $table->foreign('prospect_id')->references('id')->on('crm_lead_information');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')->references('id')->on('client_main_information');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions_details');
    }
};
