<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('list_template_verifications_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id');
            $table->string('list_template_verification_id');
            $table->string('checks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_template_verifications_tasks');
    }
};
