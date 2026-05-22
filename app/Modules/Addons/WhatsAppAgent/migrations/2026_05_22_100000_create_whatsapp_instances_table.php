<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('instance_id');
            $table->text('api_url');
            $table->text('api_key');
            $table->string('webhook_secret', 64);
            $table->boolean('default_instance')->default(false);
            $table->boolean('active')->default(true);
            $table->string('phone_number')->nullable();
            $table->string('status')->default('disconnected');
            $table->text('qr_code')->nullable();
            $table->timestamp('qr_expires_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index('slug');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};
