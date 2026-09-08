<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'title',
        'summary',
        'description',
        'release_date',
        'created_by',
        'updated_by',
        // Vínculo técnico (item roadmap #1017)
        'commit_sha',
        'migracion_desde',
        'migracion_hasta',
        'snapshot_bd',
        'aplicada_en_dev_at',
        'aplicada_en_prod_at',
        'reversible',
        'reversible_motivo',
        // Marca de procedencia del backfill (item roadmap #9990637)
        'origin',
    ];

    protected $casts = [
        'release_date'        => 'date',
        'aplicada_en_dev_at'  => 'datetime',
        'aplicada_en_prod_at' => 'datetime',
        'reversible'          => 'boolean',
    ];
}
