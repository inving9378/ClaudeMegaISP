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
        Schema::create('general_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('priority', ['Alta', 'Media', 'Baja']);
            $table->string('base_url')->nullable();
            $table->longText('description')->nullable();
            $table->string('code');
            $table->string('model')->nullable();
            $table->string('model_id')->nullable();
            $table->string('row_id')->nullable();
            $table->string('target')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_notifications');
    }
};
