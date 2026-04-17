<?php

namespace App\Models;

use App\Http\Controllers\FileController;
use App\Http\Requests\module\client\ClientPaymentRequest;
use App\Http\Traits\Models\Payments\ScopePayment\ScopePayment;
use App\Services\ConfigFinanceNotificationService;
use App\Services\Finance\Invoice\InvoiceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class Payment extends Model
{
    use HasFactory, ScopePayment, LogsActivity, SoftDeletes;
    protected $guarded = [];

    protected $appends = ['paymentable_name'];

    const FILE_FIELDS = [
        'file' => 'file'
    ];

    public static function boot()
    {
        parent::boot();
        static::created(function ($obj) {
            // $notificationFinanceService = new ConfigFinanceNotificationService();
            //  $notificationFinanceService->createEmailPayment($obj);
        });
    }

    public function getPaymentableNameAttribute()
    {
        return $this->client_main_information->client_name_with_fathers_names;
    }

    public function file()
    {
        return $this->morphOne(File::class, 'fileable');
    }

    public function payment_promise()
    {
        return $this->hasMany(PaymentPromise::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'paymentable_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function client_main_information()
    {
        return $this->belongsTo(ClientMainInformation::class, 'paymentable_id', 'client_id');
    }
    public function payment_method()
    {
        return $this->belongsTo(MethodOfPayment::class, 'payment_method_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'add_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'payment_id', 'id');
    }

    public function uploadFile($file)
    {
        if ($this->isModelClient()) {
            $file_process = new FileController;
            $module_path = 'client/' . $this->paymentable_id . '/payment';
            $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $this->id);

            $this->file()->create($properties);
            $file->storeAs('/public/' . $module_path . '/' . $this->id, $properties['name']);
        }
        return $this;
    }

    public function updateFileUpload($file)
    {
        if ($file) {
            $this->file()->first()->delete();

            $file_process = new FileController;
            $module_path = 'client/' . $this->paymentable_id . '/payment';
            $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $this->id);

            $this->file()->create($properties);
            $file->storeAs('/public/' . $module_path . '/' . $this->id, $properties['name']);
        }

        return $this;
    }

    public function isModelClient()
    {
        return $this->paymentable_type == 'App\Models\Client';
    }

    public function getPaymentMethod()
    {
        return $this->payment_method->type;
    }


    public function getPaymentPeriodByInvoice()
    {
        $invoiceService = new InvoiceService();

        if (empty($this->invoices)) {
            return null;
        }

        $periods = [];

        foreach ($this->invoices as $invoice) {
            if ($invoice->period) {
                $periods[] = $invoiceService->getStrPeriodByMonthAndYear($invoice->period);
            }
        }

        return !empty($periods) ? implode(', ', $periods) : null;
    }

    public function getRequestAndStoreMethod()
    {
        $request = new ClientPaymentRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientPaymentController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

    /**
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'payment_method_id', 'date', 'payment_period', 'paymentable_id', 'paymentable_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
