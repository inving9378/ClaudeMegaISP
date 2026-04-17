<?php

use App\Models\ClientMainInformation;
use App\Models\MapLayer;
use App\Models\MapProyect;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('map_proyects', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            $table->foreign('parent_id')->references('id')->on('map_proyects')->cascadeOnDelete();
            $table->string('classification')->default('project')->after('parent_id');
        });

        Schema::table('map_layers', function (Blueprint $table) {
            $table->string('classification')->default('project')->after('project_id');
        });

        $user = User::firstWhere('email', 'admin@admin.com');
        $p = MapProyect::create([
            'name' => 'Red',
            'classification' => 'network',
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);
        $p = MapProyect::create([
            'name' => 'Proyectos',
            'classification' => 'project',
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);
        DB::statement("update map_proyects set classification='project', parent_id=? where name not in ('Red', 'Proyectos')", [$p->id]);
        DB::statement("update map_layers set classification='project'");
        $p = MapProyect::create([
            'name' => 'Clientes',
            'classification' => 'client',
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);
        $states = ['Activo', 'Bloqueado', 'Cancelado', 'Inactivo'];
        foreach ($states as $s) {
            $sp = MapProyect::create([
                'name' => sprintf('%ss', $s),
                'parent_id' => $p->id,
                'classification' => 'client',
                'created_by' => $user->id,
                'updated_by' => $user->id
            ]);
            ClientMainInformation::whereNotNull('geodata')->where('estado', $s)->select(['id', 'geodata', 'estado'])->chunk(500, function ($clients) use ($sp) {
                $layersData = [];
                foreach ($clients as $c) {
                    $coords = explode(',', $c->geodata, 2);
                    $coords = [
                        'lat' => (float)trim($coords[0]),
                        'lng' => (float)trim($coords[1])
                    ];
                    $color = '#5bc0de';
                    if ($c->estado == 'Activo') {
                        $color = '#5cb85c';
                    } else if ($c->estado == 'Bloqueado') {
                        $color = '#b52b2b';
                    } else if ($c->estado == 'Cancelado') {
                        $color = '#808080';
                    } else if ($c->estado == 'Inactivo') {
                        $color = '#f0ad4e';
                    }
                    $layersData[] = [
                        'project_id' => $sp->id,
                        'classification' => 'client',
                        'type' => 'marker',
                        'coords' => json_encode($coords),
                        'color' => $color,
                        'route' => 'clients',
                        'text' => 'Cliente',
                        'dialog' => 'client',
                        'icon' => 'mdi-account',
                        'icon_color' => '#FFFFFF',
                        'label' => 'name',
                        'layerable_id' => $c->id,
                        'layerable_type' => 'App\Models\ClientMainInformation',
                        'data' => json_encode(
                            [
                                'client_id' => $c->id,
                                'description' => null
                            ]
                        ),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                MapLayer::insert($layersData);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        MapProyect::where('name', 'Clientes')->delete();

        Schema::table('map_proyects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('classification');
        });

        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropColumn('classification');
        });
    }
};
