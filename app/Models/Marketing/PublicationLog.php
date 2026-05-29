<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationLog extends Model
{
    public $timestamps = false;

    protected $table = 'marketing_publication_logs';

    protected $fillable = ['publication_id', 'event', 'message', 'payload', 'created_at'];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
