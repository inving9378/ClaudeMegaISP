<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('olt_billings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->string('end_subscription');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
        try {
            Artisan::call('smartolt:sync-inventory', ['--only' => 'billings']);
        } catch (\Throwable $th) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_billings');
    }
};
