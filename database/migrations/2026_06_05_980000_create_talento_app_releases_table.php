<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version_name', 32);   // "1.4"
            $table->unsignedInteger('version_code'); // 5
            $table->string('apk_url', 500);          // URL descargable
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->text('changelog')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('released_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_app_releases');
    }
};
