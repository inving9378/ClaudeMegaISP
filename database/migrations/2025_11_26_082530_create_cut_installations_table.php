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
        Schema::create('cut_installations', function (Blueprint $table) {
            $table->id();
            $table->string('service_id');
            $table->double('service_amount')->default(0);
            $table->double('installation_cost')->default(0);
            $table->string('warranty_cost');
            $table->string('constance');
            $table->boolean('activated')->default(false);
            $table->unsignedBigInteger('box_id');
            $table->foreign('box_id')->references('id')->on('cut_boxs')->onDelete('cascade');
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('client_main_information')->onDelete('cascade');
            $table->unsignedBigInteger('technical_id');
            $table->foreign('technical_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('sucursals')->onDelete('cascade');
            $table->longText('comments')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cut_installations');
    }
};
