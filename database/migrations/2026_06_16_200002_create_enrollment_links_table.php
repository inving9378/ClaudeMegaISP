<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('token', 64)->unique();
            $table->enum('channel', ['whatsapp', 'email']);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['client_id', 'used_at']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_links');
    }
};
