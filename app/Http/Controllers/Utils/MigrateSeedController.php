<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\BillingAddress;
use App\Models\BillingConfiguration;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Crm;
use App\Models\Internet;
use App\Models\Location;
use App\Models\Network;
use App\Models\Partner;
use App\Models\Custom;
use App\Models\RemindersConfiguration;
use App\Models\SystemUser;
use App\Models\TypeBilling;
use App\Models\User;
use App\Models\Voise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MigrateSeedController extends Controller
{
    protected $roles = [];
    protected $partners = [];
    protected $locations = [];
    protected $billings = [];
    protected $planes_internet = [];
    protected $planes_voz = [];
    protected $planes_custom = [];
    protected $users = [];
    protected $clients = [];

    public function __construct()
    {
        $this->billings = TypeBilling::pluck('type', 'id')->toArray();
        $this->users = User::pluck('login_user', 'id')->toArray();
    }

    public function partners($partners, $records)
    {
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
                foreach ($partners as $k => $field) {
                if ($field != 'pass') {
                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        \App\Models\Partner::insert($populate);
        \Illuminate\Support\Facades\Log::info('populate socios');

        $this->partners = Partner::pluck('name', 'id')->toArray();
        return true;
    }

    public function locations($locations, $records)
    {
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($locations as $k => $field) {
                if ($field != 'pass') {
                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }
        \App\Models\Location::insert($populate);

        $this->locations = Location::pluck('name', 'id')->toArray();
        \Illuminate\Support\Facades\Log::info('populate location');
        return true;
    }

    public function roles($roles, $records)
    {
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($roles as $k => $field) {
                if ($field != 'pass') {
                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        foreach ($populate as $val) {
            Role::create($val);
        }
        \Illuminate\Support\Facades\Log::info('populate roles');

        $this->roles = Role::pluck('name', 'id')->toArray();
        return true;
    }

    public function system_users($system_users, $records)
    {
        $populate = [];
        $rol = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($system_users as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'send_name_in_mail' || $field == 'cash_desk') {
                        $record[$k] = (isset($record[$k]) && $record[$k] == '1');
                        $populate[$j][$field] = $record[$k];
                        continue;
                    }

                    if ($field == 'partner_id') {
                        $record[$k] = count($this->getRelationMultiplePartner($record[$k])) ? $this->getRelationMultiplePartner($record[$k])[0] : 0;
                    }

                    if ($field == 'set_rol_to_user') {
                        $rol[$j] = $record[$k];
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        foreach ($populate as $key => $value) {
            $input = collect($value)
                ->only(['name', 'email', 'login_user']);
            $user = User::create($input->toArray());

            if (isset($rol[$key])) {
                $user->assignRole(Str::slug($rol[$key], '-'));
            }

            $input_system_user = collect($value)
                ->only(['phone', 'partner_id', 'timeout', 'last_ip', 'last_access', 'access_router_radius', 'send_name_in_mail', 'cash_desk']);

            $user->system_user()->create($input_system_user->toArray());
        }
        \Illuminate\Support\Facades\Log::info('populate users and system_users');
        return true;
    }

    public function permissions()
    {
        $columns = collect(config('module.administration.permission.constants.Permission.FIELDS'))->keys();
        $group_permission = ['dashboard', 'plan', 'crm', 'client','router'];
        $permissions = [];
        foreach ($group_permission as $group){
            $permissions[$group] = $columns->filter(function ($value, $key) use ($group){
                return Str::startsWith($value, $group);
            });

            foreach ($permissions[$group] as $permission){
                Permission::create([
                    'name' => $permission
                ]);
            }
        }
        \Illuminate\Support\Facades\Log::info('populate permissions');
    }

    public function setPermissionTosuperadministratorRol()
    {
        $role = Role::where('name', 'super-administrator')->first();
        $role->givePermissionTo(Permission::all()->pluck('id')->toArray());
        \Illuminate\Support\Facades\Log::info('givePermissionTo administrator rol');
    }

    public function plan_internet($planes_internet, $records)
    {
        $populate = [];
        $partners = [];
        $billings = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($planes_internet as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'relation_multiple_partner') {
                        $partners[$j] = $this->getRelationMultiplePartner($record[$k]);
                        continue;
                    } elseif ($field == 'relation_multiple_facturacion') {
                        $billings[$j] = $this->getRelationMultipleFacturacion($record[$k]);
                        continue;
                    } elseif ($field == 'relation_multiple_self') {
                        continue;
                    }

                    if ($this->isFieldTypeTransactionCategory($field)) {
                        $record[$k] = $this->getValueTransactionCategory($record[$k]);
                    }

                    if ($field == 'priority') {
                        if ($record[$k] == 'normal') {
                            $record[$k] = 'Normal';
                        }
                        if ($record[$k] == 'high') {
                            $record[$k] = 'Alta';
                        }
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        foreach ($populate as $key => $p) {
            $internet = \App\Models\Internet::create($p);
            if (isset($partners[$key])) {
                $internet->partners()->attach($partners[$key]);
            }
            if (isset($billings[$key])) {
                $internet->billings()->attach($billings[$key]);
            }
        }


        $this->planes_internet = Internet::pluck('title', 'id');
        \Illuminate\Support\Facades\Log::info('populate plan internet');
        return true;
    }

    public function plan_voz($planes_voz, $records)
    {
        $populate = [];
        $partners = [];
        $billings = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($planes_voz as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'relation_multiple_partner') {
                        $partners[$j] = $this->getRelationMultiplePartner($record[$k]);
                        continue;
                    } elseif ($field == 'relation_multiple_facturacion') {
                        $billings[$j] = $this->getRelationMultipleFacturacion($record[$k]);
                        continue;
                    } elseif ($field == 'relation_multiple_self') {
                        continue;
                    }

                    if ($this->isFieldTypeTransactionCategory($field)) {
                        $record[$k] = $this->getValueTransactionCategory($record[$k]);
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        foreach ($populate as $key => $p) {
            $voise = \App\Models\Voise::create($p);
            if (isset($partners[$key])) {
                $voise->partners()->attach($partners[$key]);
            }
            if (isset($billings[$key])) {
                $voise->billings()->attach($billings[$key]);
            }
        }

        $this->planes_voz = Voise::pluck('title', 'id');
        \Illuminate\Support\Facades\Log::info('populate plan voz');
        return true;
    }

    public function plan_custom($planes_custom, $records)
    {
        $populate = [];
        $partners = [];
        $billings = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($planes_custom as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'relation_multiple_partner') {
                        $partners[$j] = $this->getRelationMultiplePartner($record[$k]);
                        continue;
                    } elseif ($field == 'relation_multiple_facturacion') {
                        $billings[$j] = $this->getRelationMultipleFacturacion($record[$k]);
                        continue;
                    } elseif ($field == 'relation_multiple_self') {
                        continue;
                    }

                    if ($this->isFieldTypeTransactionCategory($field)) {
                        $record[$k] = $this->getValueTransactionCategory($record[$k]);
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        foreach ($populate as $key => $p) {
            $custom = \App\Models\Custom::create($p);
            if (isset($partners[$key])) {
                $custom->partners()->attach($partners[$key]);
            }
            if (isset($billings[$key])) {
                $custom->billings()->attach($billings[$key]);
            }
        }

        $this->planes_custom = Custom::pluck('title', 'id');
        \Illuminate\Support\Facades\Log::info('populate plan custom');
        return true;
    }

    public function plan_bundle($planes_bundle, $records)
    {
        $populate = [];
        $partners = [];
        $billings = [];
        $planes_internet = [];
        $planes_voz = [];
        $planes_custom = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($planes_bundle as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'relation_multiple_partner') {
                        $partners[$j] = $this->getRelationMultiplePartner($record[$k], 'lineal');
                        continue;
                    } elseif ($field == 'relation_multiple_facturacion') {
                        $billings[$j] = $this->getRelationMultipleFacturacion($record[$k], 'lineal');
                        continue;
                    } elseif ($field == 'relation_multiple_self') {
                        continue;
                    } elseif ($field == 'relation_multiple_plan_internet') {
                        $planes_internet[$j] = $this->getRelationMultiplePlanesInternet($record[$k], 'planes_internet');
                        continue;
                    } elseif ($field == 'relation_multiple_plan_voise') {
                        $planes_voz[$j] = $this->getRelationMultiplePlanesInternet($record[$k], 'planes_voz');
                        continue;
                    } elseif ($field == 'relation_multiple_plan_custom') {
                        $planes_custom[$j] = $this->getRelationMultiplePlanesInternet($record[$k], 'planes_custom');
                        continue;
                    }

                    if ($field == 'discount_period' || $field == 'contract_duration') {
                        $record[$k] = Str::replace('month(s)', '', $record[$k]);
                    }

                    if ($field == 'discount_value' || $field == 'tax') {
                        $record[$k] = Str::replace('%', '', $record[$k]);
                    }

                    if ($field == 'get_activation_fee_when') {
                        if ($record[$k] == 'first service billing') {
                            $record[$k] = 'En facturación del primer servicio';
                        } elseif ($record[$k] == 'create service') {
                            $record[$k] = 'Al crear el servicio';
                        }
                        $record[$k] = Str::replace('month(s)', '', $record[$k]);
                    }

                    if ($this->isFieldTypeTransactionCategory($field)) {
                        $record[$k] = $this->getValueTransactionCategory($record[$k]);
                    }

                    $populate[$j][$field] = $this->cleanRecord($record[$k]);
                }
            }
            $j++;
        }

        foreach ($populate as $key => $p) {
            $bundle = \App\Models\Bundle::create($p);
            if (isset($partners[$key])) {
                $bundle->partners()->attach($partners[$key]);
            }
            if (isset($billings[$key])) {
                $bundle->billings()->attach($billings[$key]);
            }
            if (isset($planes_internet[$key]) && count($planes_internet[$key])) {
                $bundle->planes_internet()->attach(collect($planes_internet[$key])->keys()->toArray()[0], ['cant' => 1]);
            }
            if (isset($planes_voz[$key]) && count($planes_voz[$key])) {
                $bundle->planes_voz()->attach(collect($planes_voz[$key])->keys()->toArray()[0], ['cant' => 1]);
            }
            if (isset($planes_custom[$key]) && count($planes_custom[$key])) {
                $bundle->planes_custom()->attach(collect($planes_custom[$key])->keys()->toArray()[0], ['cant' => 1]);
            }
        }

        \Illuminate\Support\Facades\Log::info('populate plan bundle');
        return true;
    }

    public function networks($networks, $records)
    {
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($networks as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'location_id') {
                        $record[$k] = $this->getLocationByName($record[$k]);
                    }
                    if ($field == 'type_of_use') {
                        if ($record[$k] == 'static') {
                            $record[$k] = 'Estatico';
                        }
                        if ($record[$k] == 'pool') {
                            $record[$k] = 'Pool';
                        }
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        foreach ($populate as $key => $p) {
            \App\Models\Network::create($p);
        }
        \Illuminate\Support\Facades\Log::info('populate network');
        return $populate;
    }

    public function networksIp($networksIp, $records)
    {
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($networksIp as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'location_id') {
                        $record[$k] = $this->getLocationByName($record[$k]);
                    }

                    if ($field == 'used') {
                        $populate[$j][$field] = isset($record[$k]) && Str::lower($record[$k]) == 'si';
                        continue;
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        $populate = array_chunk($populate, 300);
        foreach ($populate as $value) {
            \App\Models\NetworkIp::insert($value);
        }
        \Illuminate\Support\Facades\Log::info('populate network ip');
        return $populate;
    }

    public function crms($crms, $records){
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($crms as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'partner_id'){
                        $record[$k] = $this->getPartnerByName($record[$k]);
                    }elseif ($field == 'location_id'){
                        $record[$k] = $this->getLocationByName($record[$k]);
                    }elseif ($field == 'type_of_billing_id'){
                        $record[$k] = $this->getTypeOfBillingByName($record[$k]);
                    }elseif ($field == 'owner_id'){
                        $record[$k] = $this->getOwnerIdByLoginUser($record[$k]);
                    }elseif ($field == 'crm_status'){
                        $record[$k] = str_replace('-1-','',$record[$k]);
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        $populate_main_information = [];
        $populate_lead_information = [];
        foreach ($populate as $value) {
            $crm = Crm::create();

            $value = collect($value);
            $main_information = $value
                ->only(['ift', 'name', 'father_last_name','mother_last_name', 'email', 'phone', 'phone2', 'nif_pasaport', 'partner_id', 'location_id', 'high_date', 'street', 'external_number', 'internal_number', 'zip', 'town', 'city']);

            $input = $main_information->toArray();
            $input['crm_id'] = $crm->id;
            $populate_main_information[] = $input;

            $lead_information = $value
                ->only(['score', 'last_contacted', 'crm_status', 'owner_id', 'source']);
            $input = $lead_information->toArray();
            $input['crm_id'] = $crm->id;
            $populate_lead_information[] = $input;;
        }


        $populate = array_chunk($populate_main_information, 300);
        foreach ($populate as $value) {
            \App\Models\CrmMainInformation::insert($value);
        }
        $populate = array_chunk($populate_lead_information, 300);
        foreach ($populate as $value) {
            \App\Models\CrmLeadInformation::insert($value);
        }
        \Illuminate\Support\Facades\Log::info('populate crm');
        return true;
    }

    public function customers($clients, $records){
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($clients as $k => $field) {
                if ($field != 'pass') {
                    if ($field == 'partner_id'){
                        $record[$k] = $this->getPartnerByName($record[$k]);
                    }elseif ($field == 'location_id'){
                        $record[$k] = $this->getLocationByName($record[$k]);
                    }elseif ($field == 'type_of_billing_id'){
                        $record[$k] = $this->getTypeOfBillingByName($record[$k]);
                    }elseif ($field == 'owner_id'){
                        $record[$k] = $this->getOwnerIdByLoginUser($record[$k]);
                    }elseif ($field == 'estado'){
                        $record[$k] = str_replace('-1-','',$record[$k]);
                    } elseif ($field == 'installation_on_time' || $field == 'doubt_signed_contract'){
                        $record[$k] = isset($record[$k]) && Str::lower($record[$k]) == 'si';
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $j++;
        }

        $populate_model_client = [];
        $populate_client = [];
        $populate_main_information = [];
        $populate_additional_information = [];
        foreach ($populate as $key => $value) {
            $clientId = $value['id'];
            $populate_model_client[] = ['id' => $clientId];
            $input = ['user' => $value['user']];
            $input['client_id'] = $clientId;
            $populate_client[] = $input;

            $value = collect($value);
            $main_information = $value->only(['user_id','user','password','estado','type_of_billing_id','ift','name','email','phone','partner_id','street','zip','town','city','location_id','geo_data','discharge_date','phone2','father_last_name','mother_last_name','external_number','internal_number']);
            $additional_information = $value->only(['amount_technician_and_why','nif_pasaport','technician_attencion','modem_sn','doubt_signed_contract','user_film','power_dbm','reinstatement','gpon_ont','comment','password_wifi','installation_on_time','box_nomenclator','social_id','vendor','last_time_online','password_film','category','original_password']);

            $input = $main_information->toArray();
            $input['client_id'] = $clientId;
            $populate_main_information[] = $input;

            $input = $additional_information->toArray();
            $input['client_id'] = $clientId;
            $populate_additional_information[] = $input;

            $this->clients[$clientId] = $value['type_of_billing_id'];
        }

        $populate = array_chunk($populate_model_client, 300);
        foreach ($populate as $value) {
            \App\Models\Client::insert($value);
        }
        $populate = array_chunk($populate_client, 300);
        foreach ($populate as $value) {
            \App\Models\ClientUser::insert($value);
        }
        $populate = array_chunk($populate_main_information, 300);
        foreach ($populate as $value) {
            \App\Models\ClientMainInformation::insert($value);
        }
        $populate = array_chunk($populate_additional_information, 300);
        foreach ($populate as $value) {
            \App\Models\ClientAdditionalInformation::insert($value);
        }

        \Illuminate\Support\Facades\Log::info('populate clients');
        return true;
    }

    public function customers_view_facturation($clients, $records){
        $populate = [];
        $j = 0;
        foreach ($records as $record) {
            foreach ($clients as $k => $field) {
                if ($field != 'pass') {
                    if (in_array($field, ['billing_activated', 'create_invoice', 'autopay_invoice', 'activate_reminders', 'reminder_payment_3', 'send_financial_notification'])){
                        $record[$k] == isset($record[$k]) && $record[$k] == 1;
                    }
                    if ($field == 'type_of_message'){
                        if ($record[$k] == 0){
                            $record[$k] = 'Mail';
                        }elseif ($record[$k] == 1){
                            $record[$k] = 'SMS';
                        }else{
                            $record[$k] = 'Mail + SMS';
                        }
                    }

                    if ($field == 'amount'){
                        $record[$k] = str_replace(' pesos', '', $record[$k]);
                    }

                    $populate[$j][$field] = $record[$k] != "" ? $record[$k] : null;
                }
            }
            $populate[$j]['type_billing_id'] = isset($this->clients[$populate[$j]['client_id']]) ? $this->clients[$populate[$j]['client_id']] : null;
            $j++;
        }

        $populate_balance = [];
        $array_billing_configuration = [];
        $array_billing_address = [];
        $array_reminder_configuration = [];
        foreach ($populate as $key => $value) {
            $client = Client::find($value['client_id']);
            $value = collect($value);
            $billing_configuration = $value->only(['client_id', 'type_billing_id','payment_method_id','period','minimum_balance','send_financial_notification','create_monthly_invoice','billing_activated','billing_date','billing_expiration','grace_period','membership_percentage','create_invoice','autopay_invoice']);
            $billing_address = $value->only(['client_id', 'billing_name', 'billing_street', 'billing_zip_code', 'billing_city']);
            $reminders_configurations = $value->only(['client_id', 'activate_reminders','type_of_message','reminder_1_days','reminder_2_days','reminder_3_days','reminder_payment','reminder_payment_amount','reminder_payment_comment']);
            $balance = $value->only(['amount']);

            if ($client){
                $array_billing_configuration[] = $billing_configuration->toArray();
                $array_billing_address[] = $billing_address->toArray();
                $array_reminder_configuration[] = $reminders_configurations->toArray();

                $input = $balance->toArray();
                $input['balanceable_id'] = $client->id;
                $input['balanceable_type'] = 'App\Models\Client';
                $populate_balance[] = $input;
            }else{
                Log::info($value);
            }
        }

        $array_billing_configuration_300 = array_chunk($array_billing_configuration,300);
        foreach ($array_billing_configuration_300 as $value) {
            BillingConfiguration::insert($value);
        }

        $array_billing_address_300 = array_chunk($array_billing_address,300);
        foreach ($array_billing_address_300 as $value) {
            BillingAddress::insert($value);
        }

        $array_reminder_configuration_300 = array_chunk($array_reminder_configuration,300);
        foreach ($array_reminder_configuration_300 as $value) {
            RemindersConfiguration::insert($value);
        }

        $populate = array_chunk($populate_balance, 300);
        foreach ($populate as $value) {
            \App\Models\Balance::insert($value);
        }

        \Illuminate\Support\Facades\Log::info('populate client balance and view configuration');
        return true;
    }





















    public function getOwnerIdByLoginUser($record){
        if ($record == 'Todo') return 0;
        $allUsers = $this->users;
        $result = collect($allUsers)->filter(function ($value) use ($record) {
            return Str::contains($record, [$value]);
        })->keys()->toArray();
        if (count($result)) return $result[0];
        return null;
    }

    public function getTypeOfBillingByName($record){
        $allBillings = $this->billings;
        $str = str_replace("Pagos recurrentes", "Pagos Recurrentes", $record);
        $str = str_replace("Prepaid (Daily)", "Prepagos (Diarios)", $str);
        $record = str_replace("Prepaid (Custom)", "Prepagos (Personalizados)", $str);
        $result = collect($allBillings)->filter(function ($value) use ($record) {
            return Str::contains($record, [$value]);
        })->keys()->toArray();
        if (count($result)) return $result[0];
        return null;
    }

    public function getPartnerByName($record)
    {
        if ($record == 'Todo') return 0;
        $allPartner = $this->partners;
        $result = collect($allPartner)->filter(function ($value) use ($record) {
            return Str::contains($record, [$value]);
        })->keys()->toArray();
        if (count($result)) return $result[0];
        return null;
    }

    public function getLocationByName($record)
    {
        if ($record == 'Todo') return 0;
        $allLocation = $this->locations;
        $result = collect($allLocation)->filter(function ($value) use ($record) {
            return Str::contains($record, [$value]);
        })->keys()->toArray();
        if (count($result)) return $result[0];
        return null;
    }

    public function cleanRecord($record)
    {
        $record = Str::replace('pesos', '', $record);
        return $record != "" ?
            $record :
            null;
    }

    public function getRelationMultiplePlanesInternet($record, $relation)
    {
        $allPlan = $this->$relation;

        return $allPlan->filter(function ($value) use ($record) {
            return Str::contains($record, [$value]);
        })->toArray();
    }

    public function getRelationMultiplePartner($record, $lineal = null)
    {
        $allPartners = $this->partners;
        if (!$lineal) {
            $record = explode(',', $record);
            array_walk($record, function (&$value) {
                $value = trim($value);
            });
            $collection = collect($allPartners);
            $intersect = $collection->intersect($record);
            return $intersect->keys()->toArray();
        } else {
            return collect($allPartners)->filter(function ($value) use ($record) {
                return Str::contains($record, [$value]);
            })->keys()->toArray();
        }
    }

    public function getRelationMultipleFacturacion($record, $lineal = null)
    {
        $allBillings = $this->billings;
        $str = str_replace("Pagos recurrentes", "Pagos Recurrentes", $record);
        $str = str_replace("Prepaid (Daily)", "Prepagos (Diarios)", $str);
        $str = str_replace("Prepaid (Custom)", "Prepagos (Personalizados)", $str);
        if (!$lineal) {
            $record = explode(',', $str);
            array_walk($record, function (&$value) {
                $value = trim($value);
            });
            $collection = collect($allBillings);
            $intersect = $collection->intersect($record);
            return $intersect->keys()->toArray();
        } else {
            return collect($allBillings)->filter(function ($value) use ($str) {
                return Str::contains($str, [$value]);
            })->keys()->toArray();
        }
    }

    public function isFieldTypeTransactionCategory($field)
    {
        return in_array($field, ['transaction_category', 'transaction_category_for_calls', 'transaction_category_for_messages', 'transaction_category_for_data']);
    }

    public function getValueTransactionCategory($record)
    {
        return in_array($record, ['Default for service type', 'Default for current tariff']) ? null : $record;
    }
}
