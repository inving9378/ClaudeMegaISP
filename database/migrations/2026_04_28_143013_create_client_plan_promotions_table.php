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
        Schema::create('client_plan_promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->string('before_download_profile');
            $table->string('current_download_profile');
            $table->string('before_upload_profile');
            $table->string('current_upload_profile');
            $table->enum('status', ['active', 'canceled', 'closed'])->default('active');
            $table->timestamps();
            $table->date('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_plan_promotions');
    }
};
