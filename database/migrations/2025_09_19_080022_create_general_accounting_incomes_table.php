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
        Schema::create('general_accounting_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('category')->default('Ingreso Manual');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_accounting_incomes');
    }
};
