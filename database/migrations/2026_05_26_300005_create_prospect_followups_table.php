<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospect_id');
            $table->unsignedBigInteger('embajador_id');
            $table->enum('action', ['call', 'whatsapp', 'visit', 'sms', 'email', 'note']);
            $table->text('notes');
            $table->date('next_action_date')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('prospect_id')->references('id')->on('referral_prospects')->onDelete('cascade');
            $table->foreign('embajador_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_followups');
    }
};
