<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parental_accounts', function (Blueprint $t) {
            // Drop el FK actual antes de renombrar (MySQL exige FK fuera para rename).
            $t->dropForeign('parental_accounts_client_id_foreign');
        });

        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->renameColumn('client_id', 'user_id');
        });

        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Opción B: vínculo opcional al cliente ISP (tabla `clients`).
            $t->foreignId('client_isp_id')->nullable()->after('user_id');
            $t->foreign('client_isp_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->dropForeign(['client_isp_id']);
            $t->dropColumn('client_isp_id');
            $t->dropForeign(['user_id']);
        });

        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->renameColumn('user_id', 'client_id');
        });

        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->foreign('client_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
