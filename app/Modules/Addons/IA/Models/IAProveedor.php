<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Relations\HasMany;

class IAProveedor extends BaseModel
{
    protected $table = 'ia_proveedores';

    protected $fillable = [
        'nombre',
        'driver',
        'api_key',
        'endpoint_url',
        'modelo_default',
        'soporta_imagenes',
        'headers_personalizados',
        'config_extra',
        'activo',
        'estado',
        'ultimo_error',
        'probado_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'soporta_imagenes' => 'boolean',
        'activo' => 'boolean',
        'headers_personalizados' => 'array',
        'config_extra' => 'array',
        'probado_at' => 'datetime',
        'api_key' => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Proveedor armado en memoria por IA::proveedorPara() desde una integración
     * del Hub: nunca debe persistirse (crearía filas fantasma en ia_proveedores).
     */
    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $p) {
            if ($p->relationLoaded('integracionHub')) {
                return false;
            }
        });
    }

    public function conversaciones(): HasMany
    {
        return $this->hasMany(IAConversacion::class, 'ia_proveedor_id');
    }

    /**
     * Proveedor del Integration Hub que corresponde a cada driver, para tomar
     * su llave por defecto cuando el proveedor no trae una propia.
     */
    public const HUB_POR_DRIVER = [
        'claude' => 'anthropic',
        'openai' => 'openai',
    ];

    /**
     * Llave efectiva. El Hub es el almacén de llaves del sistema; el orden NO
     * cambia lo que ya funciona:
     *  1. config_extra.hub_integracion (slug de una integración del Hub) — enlace explícito
     *  2. api_key propia del proveedor (lo que había antes)
     *  3. integración por defecto del Hub para su driver (anthropic/openai)
     */
    public function claveApi(): ?string
    {
        $hub = \App\Services\Core\ApiIntegrationService::instance();

        $slug = data_get($this->config_extra, 'hub_integracion');
        if ($slug) {
            $integracion = $hub->getBySlug($slug);
            if ($integracion && $integracion->active && $integracion->value) {
                return $integracion->value;
            }
        }

        if (!empty($this->api_key)) {
            return $this->api_key;
        }

        $proveedorHub = self::HUB_POR_DRIVER[$this->driver] ?? null;
        return $proveedorHub ? $hub->getKey($proveedorHub) : null;
    }

    /**
     * ¿Puede leer PDF? Claude (document), OpenAI (file) y Gemini (inline_data)
     * sí, si el modelo tiene visión. openai_compatible (Ollama, etc.) y el CLI
     * no. config_extra.soporta_pdf lo fuerza en cualquier sentido.
     */
    public function soportaPdf(): bool
    {
        $forzado = data_get($this->config_extra, 'soporta_pdf');
        if ($forzado !== null) {
            return (bool) $forzado;
        }
        return $this->soporta_imagenes && in_array($this->driver, ['claude', 'openai', 'gemini'], true);
    }
}
