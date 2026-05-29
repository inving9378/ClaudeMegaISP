<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_video_templates', function (Blueprint $table) {
            $table->string('engine_version', 10)->default('1.0')->after('schema');
            $table->json('default_variables')->nullable()->after('engine_version');
            $table->integer('estimated_render_seconds')->default(30)->after('default_variables');
            $table->boolean('requires_voiceover')->default(true)->after('estimated_render_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_video_templates', function (Blueprint $table) {
            $table->dropColumn(['engine_version', 'default_variables', 'estimated_render_seconds', 'requires_voiceover']);
        });
    }
};
