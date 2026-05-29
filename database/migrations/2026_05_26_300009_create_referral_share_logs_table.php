<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_share_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('embajador_id');
            $table->enum('channel', ['whatsapp', 'sms', 'email', 'link_copy', 'bulk_send']);
            $table->integer('contacts_count')->default(1);
            $table->text('message_template')->nullable();
            $table->timestamp('shared_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('embajador_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_share_logs');
    }
};
