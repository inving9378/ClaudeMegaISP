<?php

namespace App\Models;

use App\Http\Controllers\Utils\ComunConstantsController;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'father_last_name',
        'mother_last_name',
        'email',
        'phone',
        'address',
        'city_municipality',
        'state_country',
        'code_postal',
        'rfc',
        'photography',
        'login_user',
        'password',
        'color',
        'team_id',
        'active',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['rol_name', 'url_photography', 'rule_id', 'rule_name', 'sucursal_str'];

    public static function boot()
    {
        parent::boot();

        static::saved(function ($user) {
            cache()->forget("user.{$user->id}.permissions");
        });

        static::deleted(function ($user) {
            cache()->forget("user.{$user->id}.permissions");
        });
    }

    public function scopeSearch($query, $value)
    {
        $query->where('name', 'like', "%{$value}%")
            ->orWhere('email', 'like', "%{$value}%")
            ->orWhere('father_last_name', 'like', "%{$value}%")
            ->orWhere('mother_last_name', 'like', "%{$value}%")
            ->orWhere('phone', 'like', "%{$value}%")
            ->orWhere('location', 'like', "%{$value}%");
    }

    public function system_user()
    {
        return $this->hasOne(SystemUser::class);
    }

    public function perfil()
    {
        return $this->hasOne(Perfil::class);
    }

    public function getCachedPermissions()
    {
        return cache()->remember("user.{$this->id}.permissions", 3600, function () {
            return $this->getAllPermissions()->pluck('name')->toArray();
        });
    }


    public function user_column_datatable_module()
    {
        return $this->hasMany(UserColumnDatatableModule::class);
    }

    public function observations()
    {
        return $this->hasMany(ObservationTask::class, 'created_by');
    }


    // Funciones
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->toFormattedDateString();
    }

    public function getRolNameAttribute()
    {
        return implode(", ", $this->getRoleNames()->toArray());
    }

    public function getUrlPhotographyAttribute()
    {
        if (!$this->photography) {
            // Si no hay fotografía, devuelve una imagen por defecto
            return url('assets/images/users/avatar-1.jpg');
        }

        // Comprobación directa en el disco 'public'
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($this->photography)) {
            return \Illuminate\Support\Facades\Storage::url($this->photography);
        }

        // Imagen por defecto si no existe el archivo
        return url('assets/images/users/avatar-1.jpg');
    }

    public function isAdmin()
    {
        return in_array('super-administrator', $this->getRoleNames()->toArray()) || in_array(ComunConstantsController::DEVELOPER_ROLE, $this->getRoleNames()->toArray());
    }

    public function isSuperAdmin()
    {
        return in_array('Super Administrador', $this->getRoleNames()->toArray()) || in_array('Administrador', $this->getRoleNames()->toArray()) || in_array(ComunConstantsController::DEVELOPER_ROLE, $this->getRoleNames()->toArray());
    }


    public function isDevelopment()
    {
        return in_array(ComunConstantsController::DEVELOPER_ROLE, $this->getRoleNames()->toArray());
    }

    public function isPartner()
    {
        return in_array('Socio', $this->getRoleNames()->toArray());
    }

    public function isClient()
    {
        return in_array('client', $this->getRoleNames()->toArray());
    }

    public function isNotActive()
    {
        return $this->active == 0;
    }

    public function isTechnical()
    {
        return in_array('TECNICO', $this->getRoleNames()->toArray());
    }

    public function isCounter()
    {
        return in_array('Mostrador', $this->getRoleNames()->toArray());
    }

    public function getCurrentBox()
    {
        $box = CutBox::where('user_id', $this->id)->whereDate('created_at', Carbon::now()->format('Y-m-d'))->first();
        return $box ?? null;
    }

    public function createBox()
    {
        $box = $this->getCurrentBox();
        if (!$box) {
            $box = new CutBox();
            $box->user_id = $this->id;
            $box->save();
        }
        return $box;
    }

    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function settingTables()
    {
        return $this->hasMany(SettingTable::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user', 'user_id', 'team_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_user', 'user_id', 'task_id');
    }


    public function inventoryItemStocks()
    {
        return $this->morphMany(InventoryItemStock::class, 'modelable');
    }


    public function inventory_items()
    {
        return $this->morphToMany(InventoryItem::class, 'modelable', 'inventory_item_stocks', 'modelable_id', 'inventory_item_id')
            ->withPivot('current_stock'); // Si necesitas acceder a current_stock
    }


    public function scopeClient($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', ComunConstantsController::CLIENT_ROLE);
        });
    }

    public function scopeNotClientRole($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', '!=', ComunConstantsController::CLIENT_ROLE);
        });
    }

    public function scopeSellerRole($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', ComunConstantsController::SELLER_ROLE);
        });
    }

    public function scopeLeadProject($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', ComunConstantsController::SUPER_ADMIN_ROLE);
        });
    }

    public function scopeTechnicalRole($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', ComunConstantsController::TECHNICAL_ROLE);
        });
    }

    public function scopeAdmin($query)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->orWhere('name', ComunConstantsController::SUPER_ADMINISTRADOR_CUSTOM_ROLE);
        });
    }

    public function scopeUserHasItem($query, $inventoryItemId = null)
    {
        return $query->whereHas('roles', function ($query) {
            $query->where('name', '!=', ComunConstantsController::CLIENT_ROLE);
        })->whereHas('inventoryItemStocks', function ($query) use ($inventoryItemId) {
            $query->where('current_stock', '>', 0); // Filtra registros con current_stock mayor que 0

            // Si se proporciona un inventory_item_id, filtra también por ese ítem
            if ($inventoryItemId) {
                $query->where('inventory_item_id', $inventoryItemId);
            }
        });
    }

    public function getClientNameWithFathersNamesAttribute()
    {
        return $this->name .
            ' ' .
            $this->father_last_name .
            ' ' .
            $this->mother_last_name;
    }

    public function getRuleIdAttribute()
    {
        if (isset($this->seller)) {
            $rule = HistorySellerRule::where('seller_id', $this->seller->id)->latest()->first();
            return $rule->rule_id ?? null;
        }
        return null;
    }

    public function getRuleNameAttribute()
    {
        if (isset($this->seller)) {
            $rule = HistorySellerRule::where('seller_id', $this->seller->id)->latest()->first();
            if (isset($rule)) {
                return CommissionRule::find($rule->rule_id)->name;
            }
            return $rule->rule_id ?? null;
        }
        return null;
    }

    public function getSucursalStrAttribute()
    {
        return $this->sucursal->name ?? null;
    }

    /**
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
