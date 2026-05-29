<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('key', 100)->index();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->string('group', 50)->default('general');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_settings');
    }
};
