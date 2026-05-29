<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_publication_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->default(1)->index();

            $table->string('platform', 30);          // facebook, instagram, whatsapp, email
            $table->string('channel_type', 30);      // page_feed, reels, stories, feed_square, status, blast
            $table->string('name', 100);
            $table->string('slug', 80)->unique();

            $table->string('external_id', 100)->nullable();
            $table->string('external_name', 200)->nullable();

            $table->json('platform_config')->nullable();
            $table->json('supported_aspect_ratios');
            $table->integer('max_duration_seconds')->default(60);
            $table->integer('max_file_size_mb')->default(100);

            $table->boolean('active')->default(true);
            $table->boolean('credentials_ready')->default(false);
            $table->text('credentials_status_message')->nullable();
            $table->timestamp('credentials_validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_publication_channels');
    }
};
