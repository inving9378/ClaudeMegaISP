<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_ping_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('date');
            $table->decimal('avg_ms', 8, 2)->nullable();
            $table->decimal('min_ms', 8, 2)->nullable();
            $table->decimal('max_ms', 8, 2)->nullable();
            $table->decimal('uptime_percent', 5, 2)->default(100)->comment('% del día que respondió');
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('down_checks')->default(0);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index(['client_id', 'date']);
            $table->unique(['client_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ping_statistics');
    }
};
