<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            // Logo específico para la app Talento — anula url_logo si está presente
            $table->string('talento_app_logo', 500)->nullable()->after('url_logo');
        });
    }

    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            $table->dropColumn('talento_app_logo');
        });
    }
};
