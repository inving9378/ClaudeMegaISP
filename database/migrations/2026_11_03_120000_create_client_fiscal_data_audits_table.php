<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// #1152 (FR-11 F2): bitácora aditiva de cambios a «Datos de facturación» (solo hacia adelante, sin backfill).
return new class extends Migration
{
    private const PERMISO = 'facturacion.auditoria.ver';

    public function up(): void
    {
        if (! Schema::hasTable('client_fiscal_data_audits')) {
            Schema::create('client_fiscal_data_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('campo', 60);
                $table->text('valor_anterior')->nullable();
                $table->text('valor_nuevo')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        $permiso = Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        foreach (Role::whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get() as $rol) {
            if (! $rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_fiscal_data_audits');
        Permission::where('name', self::PERMISO)->delete();
    }
};
