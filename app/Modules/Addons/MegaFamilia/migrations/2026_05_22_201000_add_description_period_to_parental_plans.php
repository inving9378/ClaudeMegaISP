<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parental_plans', function (Blueprint $t) {
            $t->text('description')->nullable()->after('slug');
            $t->decimal('price_yearly', 10, 2)->nullable()->after('price_monthly');
            $t->enum('period', ['monthly', 'yearly'])->default('monthly')->after('price_yearly');
        });
    }

    public function down(): void
    {
        Schema::table('parental_plans', function (Blueprint $t) {
            $t->dropColumn(['description', 'price_yearly', 'period']);
        });
    }
};
