<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50)->unique();
            $table->string('label', 100);
            $table->string('channel', 20)->default('whatsapp');
            $table->text('body_template');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_notification_templates');
    }
};
