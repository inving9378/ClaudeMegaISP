<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_generated_content', function (Blueprint $table) {
            $table->unsignedTinyInteger('render_progress')->default(0)->after('status');
            $table->string('render_stage', 50)->nullable()->after('render_progress');
            $table->json('render_log')->nullable()->after('render_stage');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_generated_content', function (Blueprint $table) {
            $table->dropColumn(['render_progress', 'render_stage', 'render_log']);
        });
    }
};
