<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('radius_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique(); // Acct-Session-Id
            $table->string('username');
            $table->string('framed_ip');            // IP del cliente
            $table->string('nas_ip');               // IP del MikroTik
            $table->timestamp('start_time')->nullable();
            $table->timestamp('update_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);  // Upload
            $table->unsignedBigInteger('bytes_out')->default(0); // Download
            $table->integer('session_time')->default(0);         // Segundos
            $table->string('status');               // start, interim, stop
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radius_sessions');
    }
};
