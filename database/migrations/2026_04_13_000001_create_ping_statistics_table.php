<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ping_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('ip_address', 45);
            $table->decimal('avg_ms', 8, 2)->nullable();
            $table->decimal('min_ms', 8, 2)->nullable();
            $table->decimal('max_ms', 8, 2)->nullable();
            $table->decimal('jitter_ms', 8, 2)->nullable();
            $table->tinyInteger('packet_loss')->default(0)->comment('Porcentaje 0-100');
            $table->enum('status', ['up', 'down', 'timeout']);
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index(['client_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ping_statistics');
    }
};
