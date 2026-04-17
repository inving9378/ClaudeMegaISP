<?php

namespace App\Models;

use App\Http\Requests\module\client\ClientInvoiseRequest;
use App\Services\ConfigFinanceNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInvoice extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['estado', 'total', 'payment_date', 'pay_up', 'is_sent', 'type', 'is_proforma'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    const TYPE_INVOICE_SERVICES = 1;
    const TYPE_INVOICE_SURCHARGE_DEFAULTER = 2;
    const TYPE_INVOICE_AGREEMENT = 3;

    public static function boot()
    {
        parent::boot();
        static::created(function ($obj) {
            //TODO mandar Notificacion de Pago por correo si en la configuración esta activada
            $notificationFinanceService = new ConfigFinanceNotificationService();
            $notificationFinanceService->createEmailInvoice($obj);
        });
    }


    public function client(){
        return $this->belongsTo('App\Models\Client');
    }

    public function client_bundle_service()
    {
        return $this->morphedByMany('App\Models\ClientBundleService', 'client_serviceable');
    }

    public function client_internet_service()
    {
        return $this->morphedByMany('App\Models\ClientBundleService', 'client_serviceable');
    }

    public function client_voz_service()
    {
        return $this->morphedByMany('App\Models\ClientBundleService', 'client_serviceable');
    }

    public function client_custom_service()
    {
        return $this->morphedByMany('App\Models\ClientBundleService', 'client_serviceable');
    }

    public function client_invoice_service()
    {
        return $this->hasMany('App\Models\ClientInvoiceService', 'client_invoice_id');
    }

    public function getRequestAndStoreMethod()
    {
        $request = new ClientInvoiseRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientInvoiceController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

}
