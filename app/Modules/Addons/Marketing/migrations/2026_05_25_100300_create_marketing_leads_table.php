<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->foreign('campaign_id')->references('id')->on('marketing_campaigns')->nullOnDelete();
            $table->string('channel', 50);
            $table->string('contact_identifier', 200);
            $table->string('contact_name', 200)->nullable();
            $table->json('conversation')->nullable();
            $table->integer('qualification_score')->nullable();
            $table->text('qualification_notes')->nullable();
            $table->enum('status', ['new', 'contacted', 'qualified', 'unqualified', 'scheduled', 'converted', 'lost'])->default('new');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('scheduling_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
