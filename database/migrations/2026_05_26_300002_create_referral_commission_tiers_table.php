<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tier_name', 50);
            $table->integer('speed_min_mbps');
            $table->integer('speed_max_mbps');
            $table->decimal('level_1_pct', 5, 2);
            $table->decimal('level_2_pct', 5, 2);
            $table->decimal('level_3_pct', 5, 2);
            $table->decimal('level_4_pct', 5, 2);
            $table->decimal('level_5_pct', 5, 2);
            $table->tinyInteger('active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commission_tiers');
    }
};
