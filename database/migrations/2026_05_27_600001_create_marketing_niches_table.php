<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_niches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description');

            $table->json('motivators');
            $table->json('pain_points');
            $table->json('objections');
            $table->json('vocabulary');

            $table->string('emotional_tone', 50);
            $table->string('music_mood', 50);
            $table->json('broll_tags');
            $table->string('voice_style', 50);
            $table->string('preferred_voice_id', 50);

            $table->boolean('active')->default(true);
            $table->integer('display_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_niches');
    }
};
