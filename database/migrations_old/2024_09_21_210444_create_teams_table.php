<?php

use App\Models\Module;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $module = Module::create([
            'name' => 'Team',
            'group' => 'Administration'
        ]);


        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $apechart]);


        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => 'Equipo',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'users',
                'label' => 'Integrantes',
                'type' => 25,
                'placeholder' => '',
                'position' => 2,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notClientRole'
                ]),
            ],
        ];
        $module->fields()->createMany($fields);

        $columnsDatatableByModule = [
            [
                'name' => 'id',
                'filter_name' => null,
                'label' => "ID",
                'order' => 1
            ],
            [
                'name' => 'name',
                'filter_name' => null,
                'label' => "Nombre",
                'order' => 2
            ],
            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::dropIfExists('team_user');
        // Luego eliminamos la tabla 'teams'
        Schema::dropIfExists('teams');
    }
};
