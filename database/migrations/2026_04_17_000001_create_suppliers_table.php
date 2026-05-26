<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rfc')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
        });

        try {
            $module = Module::create([
                'name' => 'Supplier',
                'group' => 'Inventory'
            ]);

            $module->columnsDatatable()->createMany([
                ['name' => 'id',             'label' => 'ID',           'order' => 1],
                ['name' => 'name',           'label' => 'Nombre',       'order' => 2],
                ['name' => 'rfc',            'label' => 'RFC',          'order' => 3],
                ['name' => 'phone',          'label' => 'Teléfono',     'order' => 4],
                ['name' => 'email',          'label' => 'Email',        'order' => 5],
                ['name' => 'status',         'label' => 'Estado',       'order' => 6],
                ['name' => 'action',         'label' => 'Acciones',     'order' => 999],
            ]);

            $role  = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
            $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();

            $permissions = [
                'supplier_view_supplier',
                'supplier_add_supplier',
                'supplier_edit_supplier',
                'supplier_delete_supplier',
            ];

            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                $role?->givePermissionTo($permission);
                $role2?->givePermissionTo($permission);
            }

            $users = collect();
            if ($role)  $users = $users->merge(User::role($role->name)->get());
            if ($role2) $users = $users->merge(User::role($role2->name)->get());

            foreach ($users->unique('id') as $user) {
                foreach ($permissions as $p) {
                    $user->givePermissionTo($p);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Supplier migration module setup: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');

        $module = Module::where('name', 'Supplier')->first();
        if ($module) {
            $module->columnsDatatable()->delete();
            $module->delete();
        }

        $permissions = [
            'supplier_view_supplier',
            'supplier_add_supplier',
            'supplier_edit_supplier',
            'supplier_delete_supplier',
        ];

        foreach ($permissions as $permissionName) {
            Permission::where('name', $permissionName)->where('guard_name', 'web')->delete();
        }
    }
};
