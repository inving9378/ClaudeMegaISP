<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('parental_devices')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('accuracy')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['device_id', 'recorded_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_locations'); }
};
