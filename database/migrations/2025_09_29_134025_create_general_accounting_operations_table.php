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
        Schema::create('general_accounting_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('general_accounting_category_id');
            $table->longText('description');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_recurrent')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_accounting_operations');
    }
};
