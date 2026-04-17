<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('general_accounting_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
        });

        DB::table('general_accounting_types')->insert([
            ['name' => 'Debe', 'created_by' => 1, 'updated_by' => 1, 'type' => 'expense'],
            ['name' => 'Haber', 'created_by' => 1, 'updated_by' => 1, 'type' => 'income'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_accounting_types');
    }
};
