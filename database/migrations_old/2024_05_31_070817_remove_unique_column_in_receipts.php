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
        Schema::table('payments', function (Blueprint $table) {
            // Drop the unique constraint from the column
            $table->dropUnique(['receipt']); // Ensure this is the correct index name
            // If the above line does not work, use the actual index name from the SHOW INDEX query
            // $table->dropUnique('receipts_receipt_unique'); // Example of specifying the index name
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add the unique constraint back to the column
            $table->unique('receipt');
        });
    }
};
