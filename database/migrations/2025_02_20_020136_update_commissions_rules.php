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
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('type_of_seller')->nullable()->after('period');
            $table->foreign('type_of_seller')->references('id')->on('seller_types')->cascadeOnUpdate();
            $table->decimal('fixed_salary', $precision = 8, $scale = 2)->nullable()->default(0)->after('type_of_seller');
            $table->integer('minimum_number_of_prospects')->nullable()->default(0)->after('fixed_salary');
            $table->integer('minimum_sales_amount')->nullable()->default(0)->after('minimum_number_of_prospects');
            $table->decimal('sales_commission', $precision = 8, $scale = 2)->nullable()->default(0)->after('minimum_sales_amount');
            $table->string('sales_commission_type')->nullable()->after('sales_commission');
            $table->decimal('additional_sales_commission', $precision = 8, $scale = 2)->nullable()->default(0)->after('sales_commission_type');
            $table->string('additional_sales_commission_type')->nullable()->after('additional_sales_commission');
            $table->json('selected_fields')->nullable()->after('additional_sales_commission_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->dropColumn(
                'fixed_salary',
                'minimum_number_of_prospects',
                'minimum_sales_amount',
                'sales_commission',
                'sales_commission_type',
                'additional_sales_commission',
                'additional_sales_commission_type',
                'selected_fields'
            );
            $table->dropConstrainedForeignId('type_of_seller');
        });
    }
};