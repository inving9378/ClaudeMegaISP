<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trench extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'trenche_type_id',
        'map_proyect_id',
        'created_by',
        'updated_by'
    ];

    /**
    * @return BelongsTo
    */
    public function upatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function infoTable(): Table
    {
        return Table::where('model_class', $this::class)->first();
    }

    public function type() : BelongsTo
    {
        return $this->belongsTo(TrencheTypes::class, "trenche_type_id", "id");
    }

    /**
    * @return BelongsTo
    */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function position()
    {
        return $this->morphOne(Position::class, 'positionable');
    }


    public function mapProyect(): BelongsTo{
        return $this->belongsTo(MapProyect::class);
    }
}
