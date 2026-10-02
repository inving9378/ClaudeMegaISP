<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiIntegrationProvider extends BaseModel
{
    use SoftDeletes;

    public const TYPES = ['ia', 'servicios'];

    /**
     * Protocolo que habla un proveedor de IA (= adaptador del módulo IA).
     * openai_compatible cubre Ollama, DeepSeek, Groq, LM Studio, etc.
     */
    public const DRIVERS = [
        'claude'            => 'Anthropic (Claude)',
        'openai'            => 'OpenAI',
        'openai_compatible' => 'Compatible con OpenAI (Ollama, DeepSeek, Groq…)',
        'gemini'            => 'Google Gemini',
    ];

    protected $table = 'api_integration_providers';

    protected $fillable = [
        'slug', 'name', 'description', 'type', 'driver', 'soporta_imagenes', 'soporta_pdf',
        'icon', 'docs_url', 'key_format', 'has_config', 'is_system', 'active',
    ];

    protected $casts = [
        'has_config'       => 'boolean',
        'is_system'        => 'boolean',
        'active'           => 'boolean',
        'soporta_imagenes' => 'boolean',
        'soporta_pdf'      => 'boolean',
    ];

    public function integrations()
    {
        return $this->hasMany(ApiIntegration::class, 'provider', 'slug');
    }
}
