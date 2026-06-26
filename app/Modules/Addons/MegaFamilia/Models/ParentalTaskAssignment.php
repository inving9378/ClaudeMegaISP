<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de una tarea (account-level) a un perfil concreto, con su propio estado.
 * El balance de cada hijo se calcula desde SUS asignaciones completadas.
 */
class ParentalTaskAssignment extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['profile', 'profile_id'];

    protected $table = 'parental_task_assignments';

    protected $fillable = [
        'task_id', 'profile_id', 'account_id', 'client_isp_id', 'status', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ParentalTask::class, 'task_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
