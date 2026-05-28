<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedBigInteger('supplier_vendor_id')->nullable();
            $table->foreign('supplier_vendor_id')->references('id')->on('supplier_vendors')->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->date('date');
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('status', ['pending', 'dispatched', 'received', 'cancelled', 'denied'])->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
