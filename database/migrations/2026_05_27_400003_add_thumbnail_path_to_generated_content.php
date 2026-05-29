<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_generated_content', function (Blueprint $table) {
            $table->string('thumbnail_path', 255)->nullable()->after('output_path');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_generated_content', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
