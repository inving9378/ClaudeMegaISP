<?php

namespace App\Models;

use App\Http\Repository\FieldTypeRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldModule extends BaseModel
{
    use HasFactory;

    const TYPE_CHECKBOX = 'input-checkbox';

    protected $guarded = [];
    protected $append = ['type_field'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }


    public function getTypeFieldAttribute()
    {
        $type = $this->type;
        $fieldTypeRepository = new FieldTypeRepository();
        return $fieldTypeRepository->getNameById($type);
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        $nameFieldType = $this->checkIfSearchingByFieldName($search);
        $columns[array_search('type_field', $columns)] = 'type';
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns, $nameFieldType) {
                foreach (collect($columns)->filter(function ($value) {
                    return $value != 'action';
                })->toArray() as $value) {
                    $query->orWhere($value, 'like', '%' . $search . '%')
                        ->orWhere($value, $nameFieldType);
                }
            });
        }
    }

    public function checkIfSearchingByFieldName($value)
    {
        $val = null;
        $fieldTypes = FieldType::all()->pluck('id', 'name');
        foreach ($fieldTypes as $key => $field) {
            if (strpos($key, $value) !== false) {
                $val = $field;
            }
        }
        return $val;
    }

    public function scopeFiltersByModuleId($query, $columns, $module_id = null, $search = null)
    {
        if (!$search) {
            return $query->where('module_id', $module_id);
        } else {
            $columns[array_search('type_field', $columns)] = 'type';
            $filteredColumns = array_filter($columns, function ($column) {
                return $column !== 'action';
            });
            return $query->where('module_id', $module_id)
                ->where(function ($query) use ($filteredColumns, $search) {
                    foreach ($filteredColumns as $column) {
                        $query->orWhere($column, 'like', '%' . $search . '%');
                    }
                });
        }
    }
}
