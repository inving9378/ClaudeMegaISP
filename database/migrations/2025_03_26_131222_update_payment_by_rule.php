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
        Schema::table(
            'payment_by_rule',
            function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->after('comments');
                $table->foreign('created_by')->references('id')->on('users')->cascadeOnUpdate();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'payment_by_rule',
            function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            }
        );
    }
};
