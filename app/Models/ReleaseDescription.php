<?php

namespace App\Models;

use App\Services\Release\ReleaseNotesRenderer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseDescription extends Model
{
    use HasFactory;

    public const FORMATO_MARKDOWN = 'markdown';
    public const FORMATO_HTML     = 'html';

    protected $fillable = ['release_id', 'title', 'description', 'formato', 'created_by', 'updated_by'];

    /**
     * #9991208 — `html` = la descripción ya RENDERIZADA y SANEADA en el backend según `formato`
     * (markdown → CommonMark con html_input=strip; html → HTMLPurifier). Es lo único que el Vue
     * pinta con v-html; `description` es la fuente cruda (markdown o HTML del editor).
     */
    protected $appends = ['html'];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function getHtmlAttribute(): string
    {
        return app(ReleaseNotesRenderer::class)->render($this->description, $this->formato ?? self::FORMATO_MARKDOWN);
    }
}
