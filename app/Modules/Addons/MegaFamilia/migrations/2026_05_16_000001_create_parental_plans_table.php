<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->integer('max_children')->default(0);
            $table->integer('max_devices')->default(0);
            $table->integer('max_parents')->default(1);
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'slug']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_plans'); }
};
