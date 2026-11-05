<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id no tenía FK (solo unsignedBigInteger NOT NULL): basta con volverlo nullable.
        DB::statement('ALTER TABLE fleet_assignments MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('fleet_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_assignments', 'operator_name')) {
                $table->string('operator_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('fleet_assignments', 'client_operator_id')) {
                $table->unsignedBigInteger('client_operator_id')->nullable()->after('operator_name');
                $table->foreign('client_operator_id')
                    ->references('id')->on('fleet_client_operators')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // No destructivo: no se borran columnas ni se vuelve NOT NULL (habría filas sin user_id).
    }
};
