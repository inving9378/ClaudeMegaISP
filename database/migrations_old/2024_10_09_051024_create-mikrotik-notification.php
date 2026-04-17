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
        Schema::create('mikrotik_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('priority', ['Alta', 'Media', 'Baja'])->nullable();
            $table->string('base_url')->nullable();
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('router_id')->nullable();
            $table->foreign('router_id')->references('id')->on('routers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_notifications');
    }
};
