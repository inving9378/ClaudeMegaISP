<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parental_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('parental_devices', 'link_token_expires_at')) {
                $table->timestamp('link_token_expires_at')->nullable()->after('link_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parental_devices', function (Blueprint $table) {
            if (Schema::hasColumn('parental_devices', 'link_token_expires_at')) {
                $table->dropColumn('link_token_expires_at');
            }
        });
    }
};
