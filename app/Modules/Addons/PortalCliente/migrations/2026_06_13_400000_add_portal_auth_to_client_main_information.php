<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->string('portal_password')->nullable()->after('password');
            $table->timestamp('portal_registered_at')->nullable()->after('portal_password');
            $table->timestamp('portal_last_login_at')->nullable()->after('portal_registered_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropColumn(['portal_password', 'portal_registered_at', 'portal_last_login_at']);
        });
    }
};
