<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('referred_by_code', 20)->nullable()->after('id');
            $table->index('referred_by_code', 'idx_referred_by_code');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_referred_by_code');
            $table->dropColumn('referred_by_code');
        });
    }
};
