<?php

namespace App\Modules\Addons\EvaluadorEmpresarial\Models;

use App\Models\BaseModel;
use App\Models\User;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EvaluacionEmpresarial extends BaseModel
{
    use SoftDeletes;

    protected $table = 'evaluaciones_empresariales';

    protected $fillable = [
        'nombre_contacto',
        'empresa',
        'email_contacto',
        'whatsapp_contacto',
        'cargo',
        'puntaje_total',
        'categoria',
        'plan_recomendado',
        'respuestas_json',
        'score_criticidad',
        'score_redundancia',
        'score_ancho_banda',
        'score_sla',
        'canal_origen',
        'vendedor_id',
        'lead_id',
        'token_publico',
        'completado_at',
    ];

    protected $casts = [
        'respuestas_json' => 'array',
        'completado_at'   => 'datetime',
    ];

    public const CATEGORIAS = ['BASICO', 'PROFESIONAL', 'CORPORATIVO', 'ENTERPRISE'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->token_publico)) {
                $model->token_publico = Str::random(64);
            }
        });
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function crm()
    {
        return $this->belongsTo(Crm::class, 'lead_id');
    }

    public function scopeCompletadas($query)
    {
        return $query->whereNotNull('completado_at');
    }

    public function scopePorCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopePorVendedor($query, int $vendedorId)
    {
        return $query->where('vendedor_id', $vendedorId);
    }
}
