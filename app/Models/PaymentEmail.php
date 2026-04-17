<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentEmail extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query =  $query->where(function ($query) use ($filter, $search, $columns) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {
                    if ($field == 'status' && !empty($values)) {
                        $query->whereIn('status', $values);
                    }
                    if ($field == "date_sent" && is_array($values) && !empty($values)) {
                        $from = Carbon::parse($values[0])->format('Y-m-d');
                        $to = $values[1];
                        if (!$to) {
                            $to = Carbon::now()->format('Y-m-d');
                        } else {
                            $to = Carbon::parse($values[1])->format('Y-m-d');
                        }
                        $query->whereDate('sent_at', '>=', $from)
                            ->whereDate('sent_at', '<=', $to);
                    }

                    if ($field == "date_created" && is_array($values) && !empty($values)) {
                        $from = Carbon::parse($values[0])->format('Y-m-d');
                        $to = $values[1];
                        if (!$to) {
                            $to = Carbon::now()->format('Y-m-d');
                        } else {
                            $to = Carbon::parse($values[1])->format('Y-m-d');
                        }
                        $query->whereDate('created_at', '>=', $from)
                            ->whereDate('created_at', '<=', $to);
                    }
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }

        return $query;
    }
}
