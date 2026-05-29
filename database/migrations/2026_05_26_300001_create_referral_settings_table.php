<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('program_active')->default(1);
            $table->decimal('threshold_amount', 10, 2)->default(1500.00);
            $table->tinyInteger('duration_months')->default(12);
            $table->tinyInteger('max_levels')->default(5);
            $table->string('program_name', 100)->default('Embajadores Meganet');
            $table->text('welcome_message')->nullable();
            $table->text('share_template_default')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
