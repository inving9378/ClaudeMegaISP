<?php

namespace App\Modules\Addons\Roadmap\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * #9991163 — Un archivo adjunto del roadmap (maqueta, captura, PDF, evidencia). Vive en el disco
 * `roadmap_adjuntos` (storage/app/roadmap/adjuntos, D1) bajo un nombre generado (`<uuid>.<ext>`,
 * D7); el nombre original es solo metadato. Puede estar suelto o amarrado a varios items (D2).
 * Un mismo contenido (sha256) es UN registro (D6): subirlo otra vez suma `veces_subido`.
 */
class RoadmapAdjunto extends Model
{
    use SoftDeletes;

    public const DISCO = 'roadmap_adjuntos';

    /** D3 — extensiones permitidas → MIME reales aceptados (finfo). Todo lo demás se rechaza. */
    public const EXTENSIONES = [
        'html' => ['text/html', 'application/xhtml+xml', 'text/plain'],
        'md'   => ['text/markdown', 'text/plain', 'text/x-markdown'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'pdf'  => ['application/pdf'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** D4 — 25 MB por archivo. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /** Punto 5 — el archivo se conserva 30 días tras el borrado lógico antes de salir de disco. */
    public const DIAS_RETENCION = 30;

    /** D5 — NUNCA se sirven inline: descarga forzada como octet-stream (XSS con sesión admin). */
    public const NUNCA_INLINE = ['html', 'htm', 'svg', 'xhtml'];

    protected $table = 'roadmap_adjuntos';

    protected $fillable = [
        'nombre_original', 'ruta', 'extension', 'hash_sha256', 'mime', 'tamano', 'descripcion',
        'veces_subido', 'subido_por', 'purgar_despues_de', 'disco_purgado_at',
    ];

    protected $casts = [
        'tamano'            => 'int',
        'veces_subido'      => 'int',
        'purgar_despues_de' => 'datetime',
        'disco_purgado_at'  => 'datetime',
    ];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(RoadmapItem::class, 'roadmap_adjunto_item', 'adjunto_id', 'item_id')
            ->withPivot(['amarrado_por', 'created_at']);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /** Ruta ABSOLUTA en disco (la que se le entrega a la terminal en el prompt, Fase 3). */
    public function rutaAbsoluta(): string
    {
        return Storage::disk(self::DISCO)->path($this->ruta);
    }

    public function existeEnDisco(): bool
    {
        return $this->disco_purgado_at === null && is_file($this->rutaAbsoluta());
    }

    public function esImagen(): bool
    {
        return str_starts_with((string) $this->mime, 'image/') && ! in_array($this->extension, self::NUNCA_INLINE, true);
    }

    /** D5 — imágenes y PDF sí pueden previsualizarse; HTML/SVG jamás. */
    public function previsualizable(): bool
    {
        return $this->esImagen() || $this->mime === 'application/pdf';
    }

    public function debeForzarDescarga(): bool
    {
        return in_array(strtolower((string) $this->extension), self::NUNCA_INLINE, true)
            || in_array((string) $this->mime, ['text/html', 'application/xhtml+xml', 'image/svg+xml'], true);
    }

    /** Punto 5 — amarrado a un item que sigue abierto → no se puede borrar sin desamarrar antes. */
    public function itemsAbiertos()
    {
        return $this->items()
            ->whereNotIn('roadmap_items.status', ['done', 'cancelled'])
            ->whereNotIn('roadmap_items.estado_aprobacion', ['completado', 'cancelado', 'rechazado']);
    }

    public function toResumen(): array
    {
        return [
            'id'                => $this->id,
            'nombre'            => $this->nombre_original,
            'extension'         => $this->extension,
            'mime'              => $this->mime,
            'tamano'            => $this->tamano,
            'descripcion'       => $this->descripcion,
            'veces_subido'      => $this->veces_subido,
            'es_imagen'         => $this->esImagen(),
            'previsualizable'   => $this->previsualizable(),
            'existe_en_disco'   => $this->existeEnDisco(),
            'subido_por'        => $this->subido_por,
            'subido_por_nombre' => $this->relationLoaded('subidoPor') && $this->subidoPor
                ? trim(($this->subidoPor->name ?? '') . ' ' . ($this->subidoPor->father_last_name ?? '')) ?: ($this->subidoPor->login_user ?? null)
                : null,
            'created_at'        => optional($this->created_at)->toIso8601String(),
            'deleted_at'        => optional($this->deleted_at)->toIso8601String(),
            'items'             => $this->relationLoaded('items')
                ? $this->items->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'estado_aprobacion' => $i->estado_aprobacion])->values()->all()
                : [],
        ];
    }
}
