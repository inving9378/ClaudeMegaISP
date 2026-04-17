<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('model_has_permissions')->truncate();
        $roles = Role::all();
        foreach ($roles as $role) {
            $users = User::whereHas('roles', function ($query) use ($role) {
                $query->where('roles.id', $role->id);
            })->get();

            foreach ($users as $user) {
                $user->syncPermissions($role->permissions);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('model_has_permissions')->truncate();
    }
};
