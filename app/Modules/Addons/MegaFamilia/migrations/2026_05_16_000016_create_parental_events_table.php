<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('parental_accounts')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('parental_profiles')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('parental_devices')->nullOnDelete();
            $table->string('action');
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void { Schema::dropIfExists('parental_events'); }
};
