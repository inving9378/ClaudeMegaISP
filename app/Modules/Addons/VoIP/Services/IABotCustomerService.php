<?php

namespace App\Modules\Addons\VoIP\Services;

use Illuminate\Support\Facades\DB;

class IABotCustomerService
{
    public function identifyCustomer(string $phoneRaw): array
    {
        $phone = preg_replace('/\D/', '', $phoneRaw);
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        $row = DB::table('client_main_information as cmi')
            ->join('clients as c', 'c.id', '=', 'cmi.client_id')
            ->select('c.id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name',
                     'cmi.email', 'cmi.phone', 'cmi.phone2', 'cmi.phone3')
            ->where(function ($q) use ($phone) {
                $q->where(DB::raw("RIGHT(REGEXP_REPLACE(cmi.phone,  '[^0-9]',''), 10)"), $phone)
                  ->orWhere(DB::raw("RIGHT(REGEXP_REPLACE(cmi.phone2, '[^0-9]',''), 10)"), $phone)
                  ->orWhere(DB::raw("RIGHT(REGEXP_REPLACE(cmi.phone3, '[^0-9]',''), 10)"), $phone);
            })
            ->whereNull('c.deleted_at')
            ->first();

        if (! $row) {
            return ['found' => false, 'type' => 'lead', 'phone' => $phone];
        }

        $fullName = trim("{$row->name} {$row->father_last_name} {$row->mother_last_name}");

        return [
            'found'           => true,
            'type'            => 'customer',
            'id'              => $row->id,
            'name'            => $fullName ?: 'Cliente',
            'email'           => $row->email,
            'phone'           => $row->phone,
            'active_services' => $this->activeServices($row->id),
        ];
    }

    private function activeServices(int $clientId): array
    {
        return DB::table('client_internet_services')
            ->where('client_id', $clientId)
            ->select('id', 'description', 'amount', 'unity', 'price', 'start_date')
            ->limit(5)
            ->get()
            ->map(fn ($s) => (array) $s)
            ->toArray();
    }
}
