<?php

namespace App\Models;

use App\Repositories\PortRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Port extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'portable_id',
        'portable_type',
        "created_by",
        "updated_by"
    ];

    public static $gibic = "gibic C+";
    public static $gibicPlus = "gibic C++";
    public static $sfp = "SFP";
    public static $sfpPlus = "SFP+";
    public static $ethernet = "ethernet";
    public static $normal = "normal";
    public static $entrada = "entrada";
    public static $fibra = "fibra";
    public static $jumper = "jumper";
    public static $fusion = "fusión";
    public static $continuo = "continuo";
    public static $box = "box";
    public static $splitterOut = "splitter_out";
    public static $splitterIn = "splitter_in";

    public function portable():MorphTo
    {
        return $this->morphTo();
    }

    public function links(): Collection
    {
        return EquipmentLink::where("input_id", $this->id)
                            ->orWhere("output_id", $this->id)
                            ->get();
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function fusionLink()
    {
        return (new PortRepository())->portLinksByObjectDataQuery()->where("ports.id", $this->id)->first();
    }

    public function linkedPorts() : Collection
    {
        return Port::select(
                    "ports.*"
                )
                ->join("equipment_links", function ($join){
                    $join->where(function($where){
                            $where->where(function($where){
                                $where->whereColumn("equipment_links.input_id", "ports.id")
                                    ->where("equipment_links.input_id", "!=", $this->id);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn("equipment_links.output_id", "ports.id")
                                    ->where("equipment_links.output_id", "!=", $this->id);
                            });
                        });
                })
                ->where("equipment_links.input_id", $this->id)
                ->orWhere("equipment_links.output_id", $this->id)
                ->get();
    }

    public function fusionPort()
    {
        return Port::select(
                    "ports_linked.*"
                )
                ->leftJoin('equipment_links', function($join){
                    $join
                    ->where(function($query){
                        $query->whereColumn('equipment_links.input_id', 'ports.id');
                    })
                    ->orWhere(function($query){
                        $query->whereColumn('equipment_links.output_id', 'ports.id');
                    });
                })
                ->leftJoin(DB::raw('ports AS ports_linked'), function ($join){
                    $join
                    ->where(function($query){
                        $query->whereColumn('equipment_links.input_id', 'ports_linked.id')
                            ->whereColumn('equipment_links.input_id', '<>','ports.id');
                    })
                    ->orWhere(function($query){
                        $query->whereColumn('equipment_links.output_id', 'ports_linked.id')
                            ->whereColumn('equipment_links.output_id', '<>','ports.id');
                    });
                })
                ->where("ports.id", $this->id)
                ->where("ports_linked.type", Port::$fusion)
                ->first();
    }

    /**
     * trae el puerto contrario de un puerto el cual esta conectado por otro puesto fucion para llegar a su objectivo
     * (Puerto actual)---->(Puerto fusion)---->(Puerto objetivo)
     */
    public function findNextToFusion()
    {
        return Port::select(
                        "ports_fision.*"
                    )
                    ->leftJoin('equipment_links', function($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('equipment_links.input_id', 'ports.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('equipment_links.output_id', 'ports.id');
                        });
                    })
                    ->leftJoin(DB::raw('ports AS ports_linked'), function ($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('equipment_links.input_id', 'ports_linked.id')
                                ->whereColumn('equipment_links.input_id', '<>','ports.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('equipment_links.output_id', 'ports_linked.id')
                                ->whereColumn('equipment_links.output_id', '<>','ports.id');
                        });
                    })
                    ->leftJoin("equipment_links as fusion_equipment_links", function($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('fusion_equipment_links.input_id', 'ports_linked.id')
                                ->whereColumn('fusion_equipment_links.output_id', '!=', 'ports.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('fusion_equipment_links.output_id', 'ports_linked.id')
                                    ->whereColumn('fusion_equipment_links.input_id', '!=', 'ports.id');
                        });
                    })
                    ->leftJoin(DB::raw('ports AS ports_fision'), function ($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('fusion_equipment_links.input_id', 'ports_fision.id')
                                ->whereColumn('fusion_equipment_links.input_id', '!=','ports_linked.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('fusion_equipment_links.output_id', 'ports_fision.id')
                                ->whereColumn('fusion_equipment_links.output_id', '!=','ports_linked.id');
                        });
                    })
                    ->where("ports.id", $this->id)
                    ->whereNotNull("ports_fision.id")
                    ->first();
    }

    /**
     * trae el enlace entre equipos contrario de un puerto el cual esta conectado por otro puerto de fusion para llegar a su objectivo
     * (Puerto actual)---->[equipment link]---->(Puerto fusion)----->[equipment link objectivo]---->(Puerto contrario)
     */
    public function findEquipmentLinNextToFusion()
    {
        return EquipmentLink::select(
                        "fusion_equipment_links.*"
                    )
                    ->from("ports")
                    ->leftJoin('equipment_links', function($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('equipment_links.input_id', 'ports.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('equipment_links.output_id', 'ports.id');
                        });
                    })
                    ->leftJoin(DB::raw('ports AS ports_linked'), function ($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('equipment_links.input_id', 'ports_linked.id')
                                ->whereColumn('equipment_links.input_id', '<>','ports.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('equipment_links.output_id', 'ports_linked.id')
                                ->whereColumn('equipment_links.output_id', '<>','ports.id');
                        });
                    })
                    ->leftJoin("equipment_links as fusion_equipment_links", function($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('fusion_equipment_links.input_id', 'ports_linked.id')
                                ->whereColumn('fusion_equipment_links.output_id', '!=', 'ports.id');
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('fusion_equipment_links.output_id', 'ports_linked.id')
                                    ->whereColumn('fusion_equipment_links.input_id', '!=', 'ports.id');
                        });
                    })
                    ->where("ports.id", $this->id)
                    ->whereNotNull("fusion_equipment_links.id")
                    ->first();
    }
}
