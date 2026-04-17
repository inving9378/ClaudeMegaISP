<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class MapLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        "classification",
        'type',
        'coords',
        'color',
        'weight',
        'distance',
        'route',
        'text',
        'dialog',
        'icon',
        'icon_color',
        'label',
        'data',
        'inputs',
        'level'
    ];

    protected $appends = [
        'key',
        'properties',
        'text_node',
        'original_client_id',
        'parent_key',
        'selected_routes',
        'model_type',
        'layers'
    ];

    protected $casts = [
        'coords' => 'json',
        'data' => 'json'
    ];

    protected $with = ['service_box'];

    protected static function boot()
    {
        parent::boot();
        static::created(function ($l) {
            if ($l->dialog === 'service_box' || $l->dialog === 'junction_box') {
                $l->createCharolas();
            } else if ($l->dialog === 'route') {
                $l->createFibers();
            }
        });
        static::updated(function ($l) {
            if (($l->dialog === 'service_box' || $l->dialog === 'junction_box') && count($l->devices) === 0) {
                $l->createCharolas();
            }
        });
        static::deleted(function ($obj) {
            if ($obj->dialog === 'route') {
                (new MapDevicePortConnection())->removeOrphansConnections();
            }
        });
    }

    public function service_box()
    {
        return $this->belongsTo(MapLayer::class, 'service_box_id');
    }

    public function devices()
    {
        return $this->hasMany(MapDevice::class, 'layer_id');
    }

    public function clients()
    {
        return $this->hasMany(MapLayer::class, 'service_box_id');
    }

    public function project()
    {
        return $this->belongsTo(MapProyect::class, 'project_id');
    }

    public function routes()
    {
        return $this->hasMany(MapLayerRoute::class, 'layer_id');
    }

    public function fibers()
    {
        return $this->hasMany(MapFiber::class, 'fiber_id');
    }

    public function layerable()
    {
        return $this->morphTo();
    }

    public function getKeyAttribute()
    {
        return sprintf('layer-%s', $this->id);
    }

    public function getParentKeyAttribute()
    {
        return $this->project->key ?? 'project-root';
    }

    public function getSelectedRoutesAttribute()
    {
        return $this->routes->pluck('id');
    }

    public function getLayersAttribute()
    {
        return count($this->routes) > 0 ?  $this->routes->pluck('route_id') : null;
    }

    public function getPropertiesAttribute()
    {
        $properties = [
            ...$this->data,
            'key' => $this->key,
            'id' => $this->id,
            'coords' => $this->coords
        ];
        if (!Arr::has($properties, 'name')) {
            $properties['name'] = $this->text_node;
        }
        return $properties;
    }

    public function getTextNodeAttribute()
    {
        if ($this->dialog == 'client') {
            $client = ClientMainInformation::find($this->data['client_id']);
            return $client->client_name_with_fathers_names ?? 'client-error-' . $this->data['client_id'];
        }
        return $this->data[$this->label];
    }

    public function getOriginalClientIdAttribute()
    {
        if ($this->dialog == 'client') {
            $client = ClientMainInformation::find($this->data['client_id']);
            return $client->client_id;
        }
        return null;
    }

    public function getModelTypeAttribute()
    {
        return $this::class;
    }

    public function scopeType($query, $val)
    {
        return $query->where('dialog', $val);
    }

    public function createSplitter($device = null)
    {
        $object = MapDevice::create([
            'name' => $this->data[$this->label] . '_spl_1',
            'data' => [
                'ports' => 8
            ],
            'layer_id' => $this->id,
            'parent_id' => $device,
            'type' => 'splitter',
        ]);
        $object->createPorts();
        return $object;
    }

    public function createCharolas()
    {
        $charoles = [];
        for ($i = 0; $i < 3; $i++) {
            $charoles[] = [
                'name' => "Charole",
                'layer_id' => $this->id,
                'type' => "charole",
            ];
        }
        MapDevice::insert($charoles);
    }

    public function createFibers()
    {
        $fibers = $this->data['fibers_amount'];
        $mapFibers = [];
        $colors = config('fibers_color');
        $date = now();
        $useSubbuffers = $fibers > 96;
        if ($useSubbuffers) {
            $mainBuffers = (int)ceil($fibers / 96);
            for ($i = 1; $i <= $mainBuffers; $i++) {
                $startHilo = ($i - 1) * 96 + 1;
                $endHilo = min($i * 96, $fibers);
                $hilosEnBuffer = $endHilo - $startHilo + 1;
                $subBuffers = (int)ceil($hilosEnBuffer / 12);
                for ($j = 1; $j <= $subBuffers; $j++) {
                    $startSub = $startHilo + ($j - 1) * 12;
                    $endSub = min($startSub + 11, $endHilo);
                    $numHilosSub = $endSub - $startSub + 1;
                    for ($k = 1; $k <= $numHilosSub; $k++) {
                        $mapFibers[] = [
                            'parent_buffer' => $i,
                            'buffer' => $j,
                            'number' => $k,
                            'color' => $colors[$k - 1],
                            'fiber_id' => $this->id,
                            'created_at' => $date,
                            'updated_at' => $date
                        ];
                    }
                }
            }
        } else {
            $buffers = (int)ceil($fibers / 12);
            $rest = $fibers % 12;
            if ($rest >= 1) {
                for ($i = 1; $i < $buffers; $i++) {
                    for ($j = 1; $j <= 12; $j++) {
                        $mapFibers[] = [
                            'parent_buffer' => 1,
                            'buffer' => $i,
                            'number' => $j,
                            'color' => $colors[$j - 1],
                            'fiber_id' => $this->id,
                            'created_at' => $date,
                            'updated_at' => $date
                        ];
                    }
                }

                for ($j = 1; $j <= $rest; $j++) {
                    $mapFibers[] = [
                        'parent_buffer' => 1,
                        'buffer' => $buffers,
                        'number' => $j,
                        'color' => $colors[$j - 1],
                        'fiber_id' => $this->id,
                        'created_at' => $date,
                        'updated_at' => $date
                    ];
                }
            } else {
                for ($i = 1; $i <= $buffers; $i++) {
                    for ($j = 1; $j <= 12; $j++) {
                        $mapFibers[] = [
                            'parent_buffer' => 1,
                            'buffer' => $i,
                            'number' => $j,
                            'color' => $colors[$j - 1],
                            'fiber_id' => $this->id,
                            'created_at' => $date,
                            'updated_at' => $date
                        ];
                    }
                }
            }
        }

        if (count($mapFibers) > 0) {
            MapFiber::insert($mapFibers);
        }
    }

    public function getLineServiceBox()
    {
        $service_box = null;
        if ($this->dialog === 'client' && $this->service_box) {
            $service_box = [
                'client_id' => $this->id,
                'client_coords' => $this->coords,
                'box_id' => $this->service_box->id,
                'box_coords' => $this->service_box->coords
            ];
        }
        return $service_box;
    }
}
