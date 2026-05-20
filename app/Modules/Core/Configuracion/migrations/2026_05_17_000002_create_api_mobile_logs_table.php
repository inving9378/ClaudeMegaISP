<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_mobile_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('method', 8);
            $table->string('endpoint', 255);
            $table->unsignedSmallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('api_mobile_logs'); }
};
