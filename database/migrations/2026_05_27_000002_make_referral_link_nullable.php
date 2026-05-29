<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_referral_profiles', function (Blueprint $table) {
            $table->string('referral_link')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('client_referral_profiles', function (Blueprint $table) {
            $table->string('referral_link')->nullable(false)->change();
        });
    }
};
