<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_sidebar_config', function (Blueprint $table) {
            if (!Schema::hasColumn('module_sidebar_config', 'permission')) {
                $table->string('permission', 150)->nullable()->after('sidebar_parent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('module_sidebar_config', function (Blueprint $table) {
            if (Schema::hasColumn('module_sidebar_config', 'permission')) {
                $table->dropColumn('permission');
            }
        });
    }
};
