<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_screenshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('section_slug', 120)->index();
            $table->string('page_slug', 150)->nullable()->index();
            $table->string('path', 500);
            $table->string('caption', 255)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_placeholder')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_screenshots');
    }
};
