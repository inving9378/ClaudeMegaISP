<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-04 — Esquema espejo `mapared_*` (aditiva, item roadmap #940).
 *
 * Mirror 1:1 de las 9 tablas `map_*` que MR-01a/MR-01b confirmaron como el sistema VIVO
 * de Mapas (map_devices, map_devices_ports, map_devices_ports_connections, map_fibers,
 * map_fibers_cut, map_layers, map_layers_routes, map_proyects, map_ports — 33k+ filas reales).
 * El "inventario formal" legacy (Box/Pole/Site/Rack/Splitter/Trench/Tube/Card/Transceiver/…,
 * grupo `mapas/*` muerto en bloque según MR-01c) NO se replica aquí: D11 lo trata como
 * catálogo de "tipos de elemento" que se modela de nuevo en MR-08+, no como tablas a espejar.
 *
 * Cada tabla añade, sobre las columnas originales, el bloque D8 (lat/lng/geom_json/bbox_*)
 * + D30 (empresa_id nullable) + origen_legacy_id (trazabilidad al id de la tabla `map_*`
 * de origen) + timestamps. Sin FKs reales entre tablas `mapared_*` (los `*_id` originales
 * apuntan a IDs legacy; MR-05 decide el remapeo al copiar) y sin tocar NUNCA las tablas
 * `map_*`/legacy — solo `Schema::create`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->enum('orientation', ['left', 'right'])->default('right');
            $table->unsignedBigInteger('layer_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->json('data')->nullable();
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_devices_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->default('in');
            $table->enum('orientation', ['left', 'right'])->default('left');
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->boolean('connected')->default(false);
            $table->smallInteger('transfer')->nullable();
            $table->string('transfer_type')->nullable();
            $table->smallInteger('card')->nullable();
            $table->longText('note')->nullable();
            $table->string('zone')->nullable();
            $table->json('data')->nullable();
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_devices_ports_connections', function (Blueprint $table) {
            $table->id();
            $table->string('from_type')->index();
            $table->unsignedBigInteger('from_id');
            $table->smallInteger('from_input')->default(0);
            $table->string('to_type')->index();
            $table->unsignedBigInteger('to_id');
            $table->smallInteger('to_input')->default(0);
            $table->string('from_element');
            $table->string('to_element');
            $table->unsignedBigInteger('from_route_id')->nullable()->index();
            $table->unsignedBigInteger('to_route_id')->nullable()->index();
            $table->string('connection_type')->default('port-to-port');
            $table->enum('type', ['dotted', 'dashed', 'default'])->default('default');
            $table->string('color')->nullable();
            $table->smallInteger('width')->default(4);
            $table->enum('animate', ['left', 'right', 'default'])->default('default');
            $table->unsignedBigInteger('layer_id')->index();
            $table->json('data')->nullable();
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_fibers', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('parent_buffer')->default(1);
            $table->smallInteger('buffer');
            $table->smallInteger('number');
            $table->string('color');
            $table->unsignedBigInteger('fiber_id')->index();
            $table->string('zone')->nullable();
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_fibers_cut', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiber_id')->index();
            $table->unsignedBigInteger('layer_id')->index();
            $table->string('state');
            $table->smallInteger('current_input')->default(0);
            $table->unsignedBigInteger('route_id')->nullable()->index();
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('classification')->default('project');
            $table->string('type');
            $table->string('color')->nullable();
            $table->string('route');
            $table->string('dialog');
            $table->string('text');
            $table->string('icon');
            $table->string('icon_color')->nullable();
            $table->integer('weight')->default(4);
            $table->decimal('distance', 12, 2)->default(0);
            $table->string('label');
            $table->unsignedBigInteger('layerable_id')->nullable();
            $table->string('layerable_type')->nullable();
            $table->unsignedBigInteger('service_box_id')->nullable()->index();
            $table->json('coords');
            $table->json('data');
            $table->smallInteger('inputs')->default(6);
            $table->integer('level')->default(1000000);
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_layers_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id')->index();
            $table->unsignedBigInteger('layer_id')->index();
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->string('direction')->default('right');
            $table->smallInteger('input')->nullable();
            $table->decimal('calculate_distance', 8, 2)->default(0);
            $table->decimal('real_distance', 8, 2)->default(0);
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_proyects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('classification')->default('project')->index();
            $table->integer('level')->default(1000000);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $this->mirrorColumns($table);
            $table->timestamps();
        });

        Schema::create('mapared_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->enum('type', ['in', 'out'])->default('out');
            $table->enum('orientation', ['left', 'right'])->default('left');
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->boolean('connected')->default(false);
            $table->smallInteger('transfer')->nullable();
            $table->string('transfer_type')->nullable();
            $table->smallInteger('card')->nullable();
            $table->longText('note')->nullable();
            $table->string('device_type')->index();
            $table->unsignedBigInteger('device_id');
            $this->mirrorColumns($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_ports');
        Schema::dropIfExists('mapared_proyects');
        Schema::dropIfExists('mapared_layers_routes');
        Schema::dropIfExists('mapared_layers');
        Schema::dropIfExists('mapared_fibers_cut');
        Schema::dropIfExists('mapared_fibers');
        Schema::dropIfExists('mapared_devices_ports_connections');
        Schema::dropIfExists('mapared_devices_ports');
        Schema::dropIfExists('mapared_devices');
    }

    /**
     * D8 (geometría) + D30 (empresa_id) + trazabilidad al registro legacy, idéntico en las 9
     * tablas espejo.
     */
    private function mirrorColumns(Blueprint $table): void
    {
        $table->decimal('lat', 10, 7)->nullable();
        $table->decimal('lng', 10, 7)->nullable();
        $table->longText('geom_json')->nullable();
        $table->decimal('bbox_min_lat', 10, 7)->nullable();
        $table->decimal('bbox_max_lat', 10, 7)->nullable();
        $table->decimal('bbox_min_lng', 10, 7)->nullable();
        $table->decimal('bbox_max_lng', 10, 7)->nullable();
        $table->unsignedBigInteger('empresa_id')->nullable();
        $table->unsignedBigInteger('origen_legacy_id')->nullable();
        $table->index(['lat', 'lng']);
        $table->index('origen_legacy_id');
    }
};
