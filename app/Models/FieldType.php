<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldType extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    const TYPE_FIELDS_FOR_CREATE_COLUMN = [
        "input-string" => 'string',
        "input-string-information" => 'string',
        "input-hidden" => 'string',
        "text" => 'text',
        "input-text-area" => 'text',
        "input-file" => 'string',
        "input-multiple-file" => 'string',
        "input-password" => 'string',
        "input-password-in-modal" => 'string',
        "input-group-generate-user" => 'string',
        "input-group-text" => 'string',
        "input-group-number" => 'integer',
        "input-group-text-init" => 'string',
        "input-group-multiple" => 'string',
        "input-number" => 'integer',
        "input-checkbox" => 'string',
        "input-checkbox-left-order" => 'string',
        "input-checkbox-with-inputs" => 'string',
        "select-component-with-group-inputs" => 'string',
        "date-time-local" => 'string',
        "input-time" => 'date',
        "select-component" => 'string',
        "select-2-component" => 'string',
        "select-2-estado-municipio-colonia-component" => 'string',
        "select-component-with-checkbox" => 'string',
        "select-component-with-checkbox-without-id" => 'string',
        "input-checkbox-after-withou-validation-error" => 'string',
        "select-single-add-items" => 'string',
        "router-network" => 'string',
        "depend-field" => 'string',
        "data-range-picker" => 'date',
        "input-price-transaction" => 'string',
    ];

    const FIELD_TYPES_TO_SELECT_IN_FIELD_MODULE = [
        "input-string",
        "input-text-area",
        "input-password",
        "input-number",
        "input-checkbox",
        "date-time-local",
        "input-time",
        "select-component",
    ];

    const FIELD_SELECTS = [
        "select-component",
        "select-2-component",
        "select-2-estado-municipio-colonia-component",
        "select-component-with-checkbox",
        "select-component-with-checkbox-without-id",
        "select-component-with-group-inputs"
    ];

    const FIELD_CHECKBOXS = [
        "input-checkbox",
        "input-checkbox-left-order",
        "input-checkbox-with-inputs",
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }


    public function scopeGetFieldTypes($query)
    {
        $names = self::FIELD_TYPES_TO_SELECT_IN_FIELD_MODULE;
        return $query->whereIn('name', $names);
    }
}
