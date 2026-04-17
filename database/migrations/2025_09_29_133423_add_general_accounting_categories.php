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
        Schema::create('general_accounting_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('general_accounting_categories')->insert([
            ['name' => 'Pago', 'type_id' => 2, 'created_by' => 1, 'is_default' => true, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Costo de Activación', 'type_id' => 2, 'created_by' => 1, 'is_default' => true, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Costo de Instalación', 'type_id' => 2, 'created_by' => 1, 'is_default' => true, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gastos Corrientes', 'type_id' => 1, 'created_by' => 1, 'is_default' => false, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inversion', 'type_id' => 1, 'created_by' => 1, 'is_default' => false, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_accounting_categories');
    }
};
