<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('parental_devices')->nullOnDelete();
            $table->enum('type', ['time_extra', 'app_unlock', 'web_unlock']);
            $table->string('detail')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void { Schema::dropIfExists('parental_requests'); }
};
