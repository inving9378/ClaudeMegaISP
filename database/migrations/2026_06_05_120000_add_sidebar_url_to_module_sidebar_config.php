<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_sidebar_config', function (Blueprint $table) {
            $table->string('sidebar_url', 255)->nullable()->after('sidebar_label');
        });
    }

    public function down(): void
    {
        Schema::table('module_sidebar_config', function (Blueprint $table) {
            $table->dropColumn('sidebar_url');
        });
    }
};
