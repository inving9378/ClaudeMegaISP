<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('section_slug', 120)->index();
            $table->string('slug', 150)->unique();
            $table->string('title', 255);
            $table->longText('body');   // markdown
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('related_module', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_pages');
    }
};
