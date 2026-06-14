<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_smartolt_config', function (Blueprint $table) {
            $table->id();
            $table->string('api_domain')->nullable();
            $table->text('api_token')->nullable();      // cifrado con Crypt
            $table->unsignedSmallInteger('ttl')->default(120);
            $table->unsignedSmallInteger('hourly_budget')->default(1000);
            $table->boolean('activa')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_smartolt_config');
    }
};
