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
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->boolean('is_proforma')->default(false)->after('type');
            $table->string('document_date')->default(false)->after('is_proforma');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
           $table->dropColumn('is_proforma');
           $table->dropColumn('document_date');
        });
    }
};
