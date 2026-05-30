<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_registry', function (Blueprint $table) {
            $table->renameColumn('version', 'installed_version');
        });
    }

    public function down(): void
    {
        Schema::table('module_registry', function (Blueprint $table) {
            $table->renameColumn('installed_version', 'version');
        });
    }
};
