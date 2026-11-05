<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

// #1144 — aditiva. Permiso nuevo solo para roles base (super-administrator + DESARROLLADOR) y tabla de pendientes.
return new class extends Migration {
    private const PERMISO = 'pppoe.csid.gestionar';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Gestionar zonas Calling-Station-Id PPPoE y re-aprender la MAC de un usuario']
        );
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);

        if (!Schema::hasTable('pppoe_csid_reaprender_pendientes')) {
            Schema::create('pppoe_csid_reaprender_pendientes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('task_id')->unique();
                $t->string('username', 100)->nullable();
                $t->string('error', 255)->nullable();
                $t->timestamp('resuelto_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_csid_reaprender_pendientes');
        Permission::where('name', self::PERMISO)->delete();
    }
};
