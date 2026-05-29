<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_rewards', function (Blueprint $table) {
            $table->timestamp('warning_sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('referral_rewards', function (Blueprint $table) {
            $table->dropColumn('warning_sent_at');
        });
    }
};
