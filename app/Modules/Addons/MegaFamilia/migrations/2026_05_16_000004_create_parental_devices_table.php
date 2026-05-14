<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('parental_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('model')->nullable();
            $table->enum('os', ['android', 'ios'])->default('android');
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->enum('status', ['online', 'offline'])->default('offline');
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('link_token', 64)->nullable()->unique();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['profile_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_devices'); }
};
