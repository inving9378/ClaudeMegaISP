<?php

namespace App\Models;

use App\Http\Repository\ClientRepository;
use App\Http\Requests\module\client\ClientCreateRequest;
use App\Http\Traits\Models\Client\Client\ScopeClient;
use App\Http\Traits\Models\Client\ClientTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Client extends BaseModel
{
    use HasFactory, ClientTrait, Notifiable, ScopeClient, SoftDeletes;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            if (auth()->check()) {
                $obj->created_by = auth()->user()->id;
                $obj->updated_by = auth()->user()->id;
            }
        });
    }

    protected $fillable = [
        'user_id',
        'last_activity',
        'position',
        'created_by',
        'updated_by',
        'fecha_corte',
        'fecha_pago',
        'migrated_from_splynt',
        'active_promise_payment',
        'fecha_fin_periodo_gracia'
    ];

    protected $guarded = [];
    protected $with = ['client_main_information', 'client_additional_information', 'billing_configuration', 'balance'];


    protected $appends = ['seller_name', 'client_name', 'price_all_services', 'fecha_corte_client'];
    const SINGLE_RELATIONS = [
        'ClientMainInformation' => [
            'relation_name' => 'client_main_information',
            'relation_field' => 'client_id',
            'local_relation' => 'id'
        ],
        'ClientAdditionalInformation' => [
            'relation_name' => 'client_additional_information',
            'relation_field' => 'client_id',
            'local_relation' => 'id'
        ]
    ];

    const RELATIONS_TO_IMPORT = [
        'ClientMainInformation' => [
            'table_relation' => 'client_main_information',
            'relation_field' => 'client_id',
        ],
        'ClientAdditionalInformation' => [
            'table_relation' => 'client_additional_information',
            'relation_field' => 'client_id',
        ],
        'User' => [
            'table_relation' => 'users',
            'relation_field' => 'client_id',
        ],
        'BillingConfiguration' => [
            'table_relation' => 'billing_configurations',
            'relation_field' => 'client_id',
        ],
    ];

    const MODEL_RELATION_TO_CREATE_FIELD_MODULE = 'App\Models\ClientMainInformation';

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['fecha_corte', 'fecha_pago', 'fecha_suspension', 'fecha_fin_periodo_gracia', 'active_promise_payment', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    private $clientRepository;


    //relations
    public function user()
    {
        return $this->hasOne(ClientUser::class);
    }

    public function user_system()
    {
        return $this->hasOne(User::class, 'client_id');
    }

    public function client_main_information()
    {
        return $this->hasOne(ClientMainInformation::class);
    }

    public function user_seller()
    {
        return $this->client_main_information->user_seller();
    }

    public function client_additional_information()
    {
        return $this->hasOne(ClientAdditionalInformation::class);
    }

    public function billing_configuration()
    {
        return $this->hasOne(BillingConfiguration::class, 'client_id');
    }

    public function reminder_configuration()
    {
        return $this->hasOne(RemindersConfiguration::class, 'client_id');
    }

    public function billing_address()
    {
        return $this->hasOne(BillingAddress::class, 'client_id');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    public function client_invoices()
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }


    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function balance()
    {
        return $this->morphOne(Balance::class, 'balanceable');
    }

    public function receipt()
    {
        return $this->morphOne(Receipt::class, 'receiptable');
    }

    public function ticket()
    {
        return $this->hasMany(Ticket::class, 'customer_lead');
    }

    public function tickets_open()
    {
        return $this->hasMany(Ticket::class, 'customer_lead')->whereNotIn('estado', ['Cerrado', 'Reciclado']);
    }

    public function tickets_closed()
    {
        return $this->hasMany(Ticket::class, 'customer_lead')->whereIn('estado', ['Cerrado', 'Reciclado']);
    }

    public function internet_service()
    {
        return $this->hasMany(ClientInternetService::class);
    }

    public function voz_service()
    {
        return $this->hasMany(ClientVozService::class);
    }

    public function custom_service()
    {
        return $this->hasMany(ClientCustomService::class);
    }

    public function bundle_service()
    {
        return $this->hasMany(ClientBundleService::class);
    }

    public function pingStatistics()
    {
        return $this->hasMany(PingStatistic::class);
    }

    public function dailyPingStatistics()
    {
        return $this->hasMany(DailyPingStatistic::class);
    }

    public function documents()
    {
        return $this->hasMany(DocumentClient::class);
    }

    public function network_ip()
    {
        return $this->hasMany(NetworkIp::class);
    }

    public function mikrotik_client_ppoe()
    {
        return $this->hasOne(MikrotikClientPpoe::class);
    }

    //TODO agregue esto
    public function mikrotik_client_hostpot_user()
    {
        return $this->hasOne(MikrotikClientHostpotUser::class);
    }

    public function client_grace_period()
    {
        return $this->hasOne(ClientGracePeriod::class);
    }

    public function commissions_details()
    {
        return $this->hasMany(CommissionDetail::class);
    }

    public function boxes()
    {
        return $this->hasMany(BoxZone::class);
    }

    public function nomenclatures()
    {
        return $this->hasMany(Nomenclature::class, 'client_id');
    }

    public function inventoryItemStocks()
    {
        return $this->morphMany(InventoryItemStock::class, 'modelable');
    }

    public function getSellerNameAttribute()
    {
        // Verifica si existe la relación client_main_information
        if ($this->client_main_information) {
            // Si existe, intenta obtener el vendedor
            $userSeller = $this->client_main_information->user_seller;
            return $userSeller ? $userSeller->name : '';
        }

        // Si no existe client_main_information, retorna una cadena vacía
        return '';
    }


    public function getClientNameAttribute()
    {
        return $this->client_main_information->name;
    }

    public function getPriceAllServicesAttribute()
    {
        $clientRepository = new ClientRepository();
        $price = $clientRepository->getCostAllService($this->id);
        return $price;
    }

    public function getFechaCorteClientAttribute()
    {
        if ($this->fecha_corte) {
            return $this->fecha_corte;
        }
        $activities = $this->activities()->orderBy('id', 'desc')->get();
        foreach ($activities as $activity) {
            $data = json_decode($activity->properties, true);
            if (isset($data['attributes']['fecha_corte'])) {
                return $data['attributes']['fecha_corte'];
            } elseif (isset($data['old']['fecha_corte'])) {
                return $data['old']['fecha_corte'];
            }
        }
        return null;
    }


    //functions


    public function routeNotificationForMail()
    {
        return $this->client_main_information->email ?? '';
    }

    public function scopeStatusClients()
    {
        $status = [];
        $clientMainInformation = ClientMainInformation::all();

        foreach ($clientMainInformation as $client) {
            $status[$client->client_id] = $client->estado;
        }
        return $status;
    }

    public function LastInvoice()
    {
        return ClientInvoice::where(DB::raw('DATE_FORMAT(created_at,"%m")'), Carbon::now()->format('m'))->orderBy('id', 'desc')->first();
    }

    public function addDocumentFromOlderCrm($crm)
    {
        $documentsCrm = $crm->documents()->with('file')->get();
        foreach ($documentsCrm as $documentCrm) {
            // create documentClient
            $document = $this->documents()->create([
                'title' => $documentCrm->title,
                'description' => $documentCrm->description,
                'show' => $documentCrm->show,
                'added_by_id' => $documentCrm->added_by_id,
            ]);

            $file = $documentCrm->file;
            $fileName = Str::afterLast($file->path, "/");
            $clientId = $this->id;
            $path = '/storage/client/' . $clientId . '/document/' . $document->id . '/' . $fileName;

            // create new file
            $document->file()->create([
                'name' => $file->name,
                'type' => $file->type,
                'path' => $path,
                'size' => $file->size,
                'preview' => $file->preview,
            ]);

            // copy file to new dir
            $cleanFrom = Str::after($file->path, '/storage');
            $from = storage_path() . '/app/public' . $cleanFrom;
            $cleanTo = Str::after($path, '/storage');
            $to = storage_path() . '/app/public' . $cleanTo;
            if (file_exists($from)) {
                if (!file_exists(dirname($to))) {
                    if (!mkdir($concurrentDirectory = dirname($to), 0777, true) && !is_dir($concurrentDirectory)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                    }
                }
                copy($from, $to);
            }
        }
    }

    public function getRequestAndStoreMethod()
    {
        $request = new ClientCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }
}
