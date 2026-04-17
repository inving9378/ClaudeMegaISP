<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class MapLink extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "map_route_id",
        "input_id",
        "input_type",
        "output_id",
        "output_type",
        "tube_id",
        "created_by",
        "updated_by"
    ];

    public function mapRoute(): BelongsTo
    {
        return $this->belongsTo(MapRoute::class);
    }

    public function mapProyect(): BelongsTo
    {
        return $this->belongsTo(MapProyect::class);
    }

    public function inputObject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'input_type', 'input_id');
    }

    public function outputObject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'output_type', 'output_id');
    }

    public function equipmentLinks(): HasMany
    {
        return $this->hasMany(EquipmentLink::class, "map_link_id", "id");
    }

    public function inputPosition()
    {
        if($this->input_type === BoxInput::class){
            return DB::table('positions')
                ->select(
                    "positions.*"
                )
                ->join("boxes", function($join){
                    $join->on("boxes.id", "positions.positionable_id")
                        ->where("positions.positionable_type", Box::class);
                })
                ->join("box_inputs", "box_inputs.box_id", "boxes.id")
                ->where("box_inputs.id", $this->input_id)
                ->first();
        }

        return DB::table('positions')
                ->select(
                    "positions.*"
                )
                ->where("positions.positionable_id", $this->input_id)
                ->where("positions.positionable_type", $this->input_type)
                ->first();
    }

    public function outputPosition()
    {
        if($this->output_type === BoxInput::class){
            return DB::table('positions')
                ->select(
                    "positions.*"
                )
                ->join("boxes", function($join){
                    $join->on("boxes.id", "positions.positionable_id")
                        ->where("positions.positionable_type", Box::class);
                })
                ->join("box_inputs", "box_inputs.box_id", "boxes.id")
                ->where("box_inputs.id", $this->output_id)
                ->first();
        }


        return DB::table('positions')
                ->select(
                    "positions.*"
                )
                ->where("positions.positionable_id", $this->output_id)
                ->where("positions.positionable_type", $this->output_type)
                ->first();
    }
}
