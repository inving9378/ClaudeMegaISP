<?php

namespace App\Models;

use App\Http\Controllers\FileController;
use App\Http\Traits\Models\Crm\CrmTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Crm extends BaseModel
{
    use HasFactory;
    use CrmTrait;
    protected $guarded = [];

    protected $table = 'crms';
    protected $appends = ['seller_name'];

    const MULTIPLE_RELATIONS = [
        'types_of_billing_id' => 'billings'
    ];

    const SINGLE_RELATIONS = [
        'CrmMainInformation' => [
            'relation_name' => 'crm_main_information',
            'relation_field' => 'crm_id',
            'local_relation' => 'id'
        ],
        'CrmLeadInformation' => [
            'relation_name' => 'crm_lead_information',
            'relation_field' => 'crm_id',
            'local_relation' => 'id'
        ]
    ];

    const MODEL_RELATION_TO_CREATE_FIELD_MODULE = 'App\Models\CrmMainInformation';


    public function crm_main_information()
    {
        return $this->hasOne(CrmMainInformation::class);
    }

    public function crm_lead_information()
    {
        return $this->hasOne(CrmLeadInformation::class);
    }

    public function log_activities()
    {
        return $this->morphMany(LogActivity::class, 'logable');
    }

    public function getSellerNameAttribute()
    {
        return $this->crm_lead_information->seller->user->name ?? '';
    }

    public function billings()
    {
        return $this->morphToMany(
            TypeBilling::class,
            'plan_billing',
            'plan_type_billings'
        )->withTimestamps();
    }

    public function documents()
    {
        return $this->hasMany(DocumentCrm::class);
    }


    public function createDocument($request)
    {
        $document = $this->createCrmDocument($request->except('file'));
        return $this->uploadAndSaveDocumentUploaded($request->file, $document, $this->id);
    }

    public function createCrmDocument($input)
    {
        if (isset($input['show'])) $input['show'] = ($input['show'] == 'true');
        $input = $this->addCreatorId($input);
        return $this->documents()->create($input);
    }

    public function addCreatorId($input)
    {
        $input['added_by_id'] = Auth::user()->id;
        return $input;
    }

    public function uploadAndSaveDocumentUploaded($file, $document, $idCrm)
    {
        $file_process = new FileController;
        $module_path = 'crm/' . $idCrm . '/document';
        $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $document->id);

        $document->file()->create($properties);
        $file->storeAs('/public/' . $module_path . '/' . $document->id, $properties['name']);

        return true;
    }

    public function savePassword($field, $val)
    {
        $this->crm_main_information()->update([
            $field => $val
        ]);
    }


    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        // $query->when($filter, function ($query, $filter, $columns) {
        //     foreach ($filter as $field => $values) {
        //         if (empty($values)) {
        //             continue;
        //         }
        //         switch ($field) {
        //             case 'owner_id':
        //                 $query->whereIn('crm_lead_information.owner_id', $values);
        //                 break;
        //             default:
        //                 if (in_array($field, $columns)) {
        //                     $query->whereIn($field, $values);
        //                 }
        //                 break;
        //         }
        //     }
        //     return $query;
        // });
        // if (!empty($filter)) {
        //     $query->where(function ($q) use ($filter, $columns) {
        //         foreach ($filter as $field => $values) {
        //             if (empty($values)) {
        //                 continue;
        //             }
        //             switch ($field) {
        //                 case 'owner_id':
        //                     $q->whereHas('crm_lead_information', function ($subQuery) use ($values) {
        //                         $subQuery->whereIn('owner_id', $values);
        //                     });
        //                     break;
        //                 default:
        //                     if (in_array($field, $columns)) {
        //                         $q->whereIn($field, $values);
        //                     }
        //                     break;
        //             }
        //         }
        //     });
        // }
        // if (!empty($search)) {
        //     $query->where(function ($q) use ($search, $columns) {
        //         foreach ($columns as $column) {
        //             if ($column === 'action') {
        //                 continue;
        //             }
        //             switch ($column) {
        //                 case 'owner_id':
        //                     $q->orWhereHas('crm_lead_information.seller.user', function ($subQuery) use ($search) {
        //                         $subQuery->where('name', 'like', '%' . $search . '%');
        //                     });
        //                     break;

        //                 default:
        //                     $qualifiedColumn = ($column === 'id') ? 'crms.id' : $column;
        //                     $q->orWhere($qualifiedColumn, 'like', '%' . $search . '%');
        //                     break;
        //             }
        //         }
        //     });
        // }
        return $query;
    }
}
