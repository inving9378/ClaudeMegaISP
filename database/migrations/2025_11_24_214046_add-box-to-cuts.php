<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $tables = ['cut_suppliers_expenses', 'cuts_observations', 'cut_extras_incomes'];
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('box_id')->after('created_by')->nullable();
                $table->foreign('box_id')->references('id')->on('cut_boxs')->onDelete('cascade');
                $table->dropConstrainedForeignId('seller_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('box_id');
                $table->unsignedBigInteger('seller_id')->nullable();
                $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
            });
        }
    }
};
