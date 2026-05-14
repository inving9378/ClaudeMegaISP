<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['home', 'school', 'family'])->default('home');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->boolean('alert_on_enter')->default(false);
            $table->boolean('alert_on_exit')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['profile_id', 'active']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_geofences'); }
};
