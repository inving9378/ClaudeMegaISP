<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_work_order_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 60)->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->boolean('is_billable')->default(true);
            $table->boolean('requires_validation')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        $types = [
            ['name' => 'Instalación',               'category' => 'campo',    'points' => 3, 'is_billable' => 1, 'requires_validation' => 1],
            ['name' => 'Garantía pagable',           'category' => 'campo',    'points' => 1, 'is_billable' => 1, 'requires_validation' => 0],
            ['name' => 'Garantía no pagable',        'category' => 'campo',    'points' => 0, 'is_billable' => 0, 'requires_validation' => 0],
            ['name' => 'Actividad alternativa',      'category' => 'interno',  'points' => 1, 'is_billable' => 1, 'requires_validation' => 0],
        ];
        foreach ($types as $t) {
            DB::table('talento_work_order_types')->insertOrIgnore(array_merge($t, [
                'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_order_types');
    }
};
