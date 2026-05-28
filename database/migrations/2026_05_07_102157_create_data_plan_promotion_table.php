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
        Schema::create('data_plan_promotion', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('upload');
            $table->string('download');
            $table->integer('duration')->default(0);
            $table->enum('type_duration', ['day', 'month'])->nullable();
            $table->boolean('defined_by_user')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_plan_promotion');
    }
};
