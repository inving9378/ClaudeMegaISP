<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Firma electrónica independiente del proveedor (item #565, épica #554).
 * Aditiva e idempotente. El reparto de permisos a roles lo decide Irving.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('firma_solicitudes')) {
            Schema::create('firma_solicitudes', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->unsignedBigInteger('emision_id')->nullable()->index(); // documento_emisiones (item 06)
                $t->string('estado', 30)->default('pendiente_envio')->index();
                $t->boolean('orden_secuencial')->default(false);
                $t->string('proveedor', 40)->default('sandbox');
                $t->string('id_externo', 80)->nullable()->index();
                $t->string('sha256_original', 64)->nullable();
                $t->string('sha256_firmado', 64)->nullable();
                $t->timestamp('expira_at')->nullable();
                $t->unsignedBigInteger('creado_por')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('firma_participantes')) {
            Schema::create('firma_participantes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('solicitud_id')->index();
                $t->string('nombre');
                $t->string('correo');
                $t->string('rol', 40)->default('firmante');
                $t->unsignedSmallInteger('orden')->default(1);
                $t->string('estado', 30)->default('pendiente')->index();
                $t->string('token_hash', 64)->nullable()->unique();
                $t->timestamp('expira_at')->nullable();
                $t->timestamp('usado_at')->nullable();
                $t->string('otp_hash', 64)->nullable();
                $t->timestamp('otp_expira_at')->nullable();
                $t->unsignedSmallInteger('intentos')->default(0);
                $t->timestamp('firmado_at')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('firma_eventos')) {
            Schema::create('firma_eventos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('solicitud_id')->index();
                $t->string('proveedor_evento_id', 120)->unique();
                $t->string('tipo', 40);
                $t->json('payload')->nullable();
                $t->timestamp('recibido_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('firma_evidencias')) {
            Schema::create('firma_evidencias', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('solicitud_id')->index();
                $t->unsignedBigInteger('participante_id')->index();
                $t->string('identidad_declarada');
                $t->string('correo');
                $t->string('metodo_autenticacion', 40);
                $t->boolean('consentimiento')->default(false);
                $t->string('zona_horaria', 60);
                $t->string('id_transaccion', 80);
                $t->string('sha256_original', 64);
                $t->string('sha256_firmado', 64)->nullable();
                $t->string('version', 20)->default('1');
                $t->timestamp('firmado_at');
                $t->timestamps();
            });
        }

        foreach (['firma-electronica.solicitudes.view', 'firma-electronica.solicitudes.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($name);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: es evidencia.
    }
};
