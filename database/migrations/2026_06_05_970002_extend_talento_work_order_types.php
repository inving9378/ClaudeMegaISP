<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_work_order_types', function (Blueprint $table) {
            // Indica si completar esta OT activa el período de garantía del cliente.
            $table->boolean('inicia_garantia')->default(false)->after('requires_validation');
        });

        // Renombrar tipos existentes y ajustar puntos/flags para alinear con v1.
        // Instalación (id=1): 3 pts → 9 pts, inicia_garantia=true
        DB::table('talento_work_order_types')->where('id', 1)->update([
            'name'              => 'Instalación nueva',
            'points'            => 9,
            'is_billable'       => true,
            'requires_validation'=> true,
            'inicia_garantia'   => true,
        ]);

        // Garantía pagable (id=2) → Soporte/reparación
        DB::table('talento_work_order_types')->where('id', 2)->update([
            'name'        => 'Soporte/reparación (garantía)',
            'points'      => 3,
            'is_billable' => true,
        ]);

        // Garantía no pagable (id=3) → Cambio de equipo
        DB::table('talento_work_order_types')->where('id', 3)->update([
            'name'        => 'Cambio de equipo',
            'points'      => 0,
            'is_billable' => false,
        ]);

        // Actividad alternativa (id=4) → Reubicación
        DB::table('talento_work_order_types')->where('id', 4)->update([
            'name'        => 'Reubicación',
            'points'      => 0,
            'is_billable' => false,
        ]);

        // Nuevo tipo: Baja / retiro
        DB::table('talento_work_order_types')->insert([
            'name'               => 'Baja/retiro',
            'category'           => 'campo',
            'points'             => 0,
            'is_billable'        => false,
            'requires_validation'=> false,
            'inicia_garantia'    => false,
            'active'             => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        // Revertir datos (best-effort)
        DB::table('talento_work_order_types')->where('id', 1)->update([
            'name' => 'Instalación', 'points' => 3, 'inicia_garantia' => false,
        ]);
        DB::table('talento_work_order_types')->where('id', 2)->update([
            'name' => 'Garantía pagable', 'points' => 1,
        ]);
        DB::table('talento_work_order_types')->where('id', 3)->update([
            'name' => 'Garantía no pagable', 'points' => 0, 'is_billable' => false,
        ]);
        DB::table('talento_work_order_types')->where('id', 4)->update([
            'name' => 'Actividad alternativa', 'points' => 1, 'is_billable' => true,
        ]);
        DB::table('talento_work_order_types')->where('name', 'Baja/retiro')->delete();

        Schema::table('talento_work_order_types', function (Blueprint $table) {
            $table->dropColumn('inicia_garantia');
        });
    }
};
