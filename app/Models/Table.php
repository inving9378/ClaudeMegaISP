<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        "name",
        "model_class",
        "repository_class",
        "type",
        "column_name",
        "search_column_name",
        "label",
        "has_position",
        "has_connection",
        "in_site",
        "created_by",
        "updated_by"
    ];

    //Enums
    public static $positionable = "positionable";
    public static $static = "static";
}
