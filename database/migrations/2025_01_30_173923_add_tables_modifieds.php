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
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_movements');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->string('initial_stock')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->boolean('serial_number_enable')->default(0);
            $table->string('serial_number')->nullable();
            $table->boolean('status_item_enable')->default(0);
            $table->enum('status_item', ['new', 'used', 'repair', 'warranty', 'broken'])->default('new');

            $table->unsignedBigInteger('inventory_item_type_id');
            $table->foreign('inventory_item_type_id')->references('id')->on('inventory_item_types');


            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->enum('type', ['Entrada', 'Salida']);
            $table->string('quantity');
            $table->longText('description')->nullable();

            // Relación polimórfica hacia dónde fue el movimiento
            $table->unsignedBigInteger('movementable_to_id');
            $table->string('movementable_to_type');
            $table->index(['movementable_to_type', 'movementable_to_id'], 'idx_movement_to');

            // Relación polimórfica desde dónde fue el movimiento
            $table->unsignedBigInteger('movementable_from_id');
            $table->string('movementable_from_type');
            $table->index(['movementable_from_type', 'movementable_from_id'], 'idx_movement_from');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_movements');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->string('initial_stock')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->boolean('serial_number_enable')->default(0);
            $table->string('serial_number')->nullable();
            $table->boolean('status_item_enable')->default(0);
            $table->enum('status_item', ['new', 'used', 'repair', 'warranty', 'broken'])->default('new');
            $table->unsignedBigInteger('inventory_item_type_id');
            $table->foreign('inventory_item_type_id')->references('id')->on('inventory_item_types');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->enum('type', ['Entrada', 'Salida']);
            $table->string('quantity');
            $table->longText('description')->nullable();

            // Relación polimórfica hacia dónde fue el movimiento
            $table->unsignedBigInteger('movementable_to_id');
            $table->string('movementable_to_type');
            $table->index(['movementable_to_type', 'movementable_to_id'], 'idx_movement_to');

            // Relación polimórfica desde dónde fue el movimiento
            $table->unsignedBigInteger('movementable_from_id');
            $table->string('movementable_from_type');
            $table->index(['movementable_from_type', 'movementable_from_id'], 'idx_movement_from');

            $table->softDeletes();
            $table->timestamps();
        });
    }
};
