<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = Permission::create([
            'name' => 'crm_view_of_convert_crm_to_client',
            'guard' => 'web',
        ]);

        $role = Role::where('name', ComunConstantsController::SELLER_ROLE)->first();
        $role->givePermissionTo($permission);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'crm_view_of_convert_crm_to_client')->first();
        $role = Role::where('name', ComunConstantsController::SELLER_ROLE)->first();
        $role->revokePermissionTo($permission);
        $permission->delete();
    }
};
