<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_sections', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug')->index();
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['module_slug', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_sections');
    }
};
