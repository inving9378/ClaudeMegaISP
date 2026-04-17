<?php

use App\Models\MapDevice;
use App\Models\MapDevicePort;
use App\Models\MapOlt;
use App\Models\MapOrganizer;
use App\Models\MapSiteRack;
use App\Models\MapSplitter;
use App\Models\MapSwitch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('map_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->smallInteger('position_x')->default(20);
            $table->smallInteger('position_y')->default(20);
            $table->enum('orientation', ['left', 'right'])->default('right');
            $table->unsignedBigInteger('layer_id')->nullable();
            $table->foreign('layer_id')->references('id')->on('map_layers')->onDelete('cascade');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('map_devices')->onDelete('cascade');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('map_devices_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->default('in');
            $table->enum('orientation', ['left', 'right'])->default('left');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->foreign('device_id')->references('id')->on('map_devices')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')->references('id')->on('client_main_information')->cascadeOnDelete();
            $table->boolean('connected')->default(false);
            $table->smallInteger('transfer')->nullable();
            $table->string('transfer_type')->nullable();
            $table->smallInteger('card')->nullable();
            $table->longText('note')->nullable();
            $table->timestamps();
        });

        Schema::create('map_devices_ports_connections', function (Blueprint $table) {
            $table->id();
            $table->morphs('from');
            $table->morphs('to');
            $table->enum('type', ['dotted', 'dashed', 'default'])->default('default');
            $table->string('color')->nullable();
            $table->smallInteger('width')->default(4);
            $table->enum('animate', ['left', 'right', 'default'])->default('default');
            $table->unsignedBigInteger('layer_id');
            $table->foreign('layer_id')->references('id')->on('map_layers')->onDelete('cascade');
            $table->timestamps();
        });

        $splitters = MapSplitter::where('splitter_users', false)->get();
        foreach ($splitters as $s) {
            $device = MapDevice::create([
                'name' => $s->name,
                'type' => 'splitter',
                'position_x' => $s->position_x,
                'position_y' => $s->position_y,
                'orientation' => $s->orientation,
                'layer_id' => $s->box_id,
                'data' => [
                    'ports' => $s->ports
                ]
            ]);
            foreach ($s->ports_array as $p) {
                MapDevicePort::create([
                    'name' => $p->name,
                    'type' => $p->type,
                    'transfer' => $p->transfer,
                    'transfer_type' => $p->transfer_type,
                    'orientation' => $p->orientation,
                    'device_id' => $device->id,
                    'client_id' => $p->client_id,
                    'connected' => false,
                    'note' => $p->note
                ]);
            }
        }
        $splitters = MapSplitter::where('splitter_users', true)->get();
        foreach ($splitters as $s) {
            foreach ($s->ports_array as $p) {
                $device = MapDevice::create([
                    'name' => $s->name,
                    'type' => 'client',
                    'position_x' => $p->position_x,
                    'position_y' => $p->position_y,
                    'orientation' => $s->orientation,
                    'layer_id' => $s->box_id,
                    'data' => [
                        'ports' => 1
                    ]

                ]);
                MapDevicePort::create([
                    'name' => $p->name,
                    'type' => $p->type,
                    'transfer' => $p->transfer,
                    'transfer_type' => $p->transfer_type,
                    'orientation' => $p->orientation,
                    'device_id' => $device->id,
                    'client_id' => $p->client_id,
                    'connected' => false,
                    'note' => $p->note
                ]);
            }
        }

        $racks = MapSiteRack::all();
        foreach ($racks as $r) {
            $rack = MapDevice::create([
                'name' => $r->name,
                'type' => 'rack',
                'position_x' => $r->position_x,
                'position_y' => $r->position_y,
                'layer_id' => $r->site_id,
            ]);
            foreach ($r->organizers as $d) {
                $organizer = MapDevice::create([
                    'name' => $d->name,
                    'type' => 'organizer',
                    'position_x' => $d->position_x,
                    'position_y' => $d->position_y,
                    'parent_id' => $rack->id,
                    'data' => [
                        'rows' => $d->rows,
                        'columns' => $d->columns,
                    ]
                ]);
                foreach ($d->ports as $p) {
                    MapDevicePort::create([
                        'name' => $p->name,
                        'type' => $p->type,
                        'transfer' => $p->transfer,
                        'transfer_type' => $p->transfer_type,
                        'orientation' => $p->orientation,
                        'device_id' => $organizer->id,
                        'client_id' => $p->client_id,
                        'connected' => false,
                        'note' => $p->note
                    ]);
                }
            }
            foreach ($r->switchs as $d) {
                $switch = MapDevice::create([
                    'name' => $d->name,
                    'type' => 'switch',
                    'position_x' => $d->position_x,
                    'position_y' => $d->position_y,
                    'parent_id' => $rack->id,
                    'data' => [
                        'ports_eth' => isset($d->ports_eth) ? $d->ports_eth : 0,
                        'ports_1_gb' => isset($d->ports_1_gb) ? $d->ports_1_gb : 0,
                        'ports_10_gb' => isset($d->ports_10_gb) ? $d->ports_10_gb : 0,
                        'ports_100_gb' => isset($d->ports_100_gb) ? $d->ports_100_gb : 0
                    ]
                ]);
                foreach ($d->ports as $p) {
                    MapDevicePort::create([
                        'name' => $p->name,
                        'type' => $p->type,
                        'transfer' => $p->transfer,
                        'transfer_type' => $p->transfer_type,
                        'orientation' => $p->orientation,
                        'device_id' => $switch->id,
                        'client_id' => $p->client_id,
                        'connected' => false,
                        'note' => $p->note
                    ]);
                }
            }
            foreach ($r->olts as $d) {
                $olt = MapDevice::create([
                    'name' => $d->name,
                    'type' => 'olt',
                    'position_x' => $d->position_x,
                    'position_y' => $d->position_y,
                    'parent_id' => $rack->id,
                    'data' => [
                        'cards' => $d->cards,
                        'ports_x_card' => $d->ports_x_card
                    ]
                ]);
                foreach ($d->ports as $p) {
                    MapDevicePort::create([
                        'name' => $p->name,
                        'type' => $p->type,
                        'transfer' => $p->transfer,
                        'transfer_type' => $p->transfer_type,
                        'orientation' => $p->orientation,
                        'device_id' => $olt->id,
                        'client_id' => $p->client_id,
                        'connected' => false,
                        'card' => $p->card,
                        'note' => $p->note
                    ]);
                }
            }
        }
        $organizers = MapOrganizer::where('rack_type', 'App\Models\MapLayer')->get();
        foreach ($organizers as $d) {
            $organizer = MapDevice::create([
                'name' => $d->name,
                'type' => 'organizer',
                'position_x' => $d->position_x,
                'position_y' => $d->position_y,
                'layer_id' => $d->rack_id,
                'data' => [
                    'rows' => $d->rows,
                    'columns' => $d->columns
                ]
            ]);
            foreach ($d->ports as $p) {
                MapDevicePort::create([
                    'name' => $p->name,
                    'type' => $p->type,
                    'transfer' => $p->transfer,
                    'transfer_type' => $p->transfer_type,
                    'orientation' => $p->orientation,
                    'device_id' => $organizer->id,
                    'client_id' => $p->client_id,
                    'connected' => false,
                    'note' => $p->note
                ]);
            }
        }
        $switchs = MapSwitch::where('rack_type', 'App\Models\MapLayer')->get();
        foreach ($switchs as $d) {
            $switch = MapDevice::create([
                'name' => $d->name,
                'type' => 'switch',
                'position_x' => $d->position_x,
                'position_y' => $d->position_y,
                'layer_id' => $d->rack_id,
                'data' => [
                    'ports_eth' => isset($d->ports_eth) ? $d->ports_eth : 0,
                    'ports_1_gb' => isset($d->ports_1_gb) ? $d->ports_1_gb : 0,
                    'ports_10_gb' => isset($d->ports_10_gb) ? $d->ports_10_gb : 0,
                    'ports_100_gb' => isset($d->ports_100_gb) ? $d->ports_100_gb : 0
                ]
            ]);
            foreach ($d->ports as $p) {
                MapDevicePort::create([
                    'name' => $p->name,
                    'type' => $p->type,
                    'transfer' => $p->transfer,
                    'transfer_type' => $p->transfer_type,
                    'orientation' => $p->orientation,
                    'device_id' => $switch->id,
                    'client_id' => $p->client_id,
                    'connected' => false,
                    'note' => $p->note
                ]);
            }
        }
        $olts = MapOlt::where('rack_type', 'App\Models\MapLayer')->get();
        foreach ($olts as $d) {
            $olt = MapDevice::create([
                'name' => $d->name,
                'type' => 'olt',
                'position_x' => $d->position_x,
                'position_y' => $d->position_y,
                'layer_id' => $d->rack_id,
                'data' => [
                    'cards' => $d->cards,
                    'ports_x_card' => $d->ports_x_card
                ]
            ]);
            foreach ($d->ports as $p) {
                MapDevicePort::create([
                    'name' => $p->name,
                    'type' => $p->type,
                    'transfer' => $p->transfer,
                    'transfer_type' => $p->transfer_type,
                    'orientation' => $p->orientation,
                    'device_id' => $olt->id,
                    'client_id' => $p->client_id,
                    'connected' => false,
                    'card' => $p->card,
                    'note' => $p->note
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_devices_ports_connections');
        Schema::dropIfExists('map_devices_ports');
        Schema::dropIfExists('map_devices');
    }
};
