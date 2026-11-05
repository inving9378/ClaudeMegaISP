<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cursos de Academia por rol (2026-10-24: "los cursos deben mostrarse por rol: si son generales, para
 * todos; los específicos de cada rol, solamente a ese rol"). `roles` = roles de Administración a los
 * que va el curso; vacío = general (todos). Los cursos que decían «técnicos» en el departamento quedan
 * para los roles de técnico que existan. `department` se conserva tal cual.
 */
return new class extends Migration
{
    private const TECNICOS = ['TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];

    public function up(): void
    {
        if (! Schema::hasColumn('talento_courses', 'roles')) {
            Schema::table('talento_courses', function (Blueprint $table) {
                $table->json('roles')->nullable()->after('department');
            });
        }

        $tecnicos = DB::table('roles')->whereIn('name', self::TECNICOS)->pluck('name')->values()->all();
        if ($tecnicos) {
            DB::table('talento_courses')
                ->whereNull('roles')
                ->whereIn(DB::raw('LOWER(department)'), ['técnicos', 'tecnicos'])
                ->update(['roles' => json_encode($tecnicos)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_courses', 'roles')) {
            Schema::table('talento_courses', function (Blueprint $table) {
                $table->dropColumn('roles');
            });
        }
    }
};
