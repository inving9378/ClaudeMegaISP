<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rango de numeración: la estructura de la empresa, antes que las extensiones.
 *
 * `desde` y `hasta` son TEXTO (respetan ceros a la izquierda), así que las
 * comparaciones de traslape se hacen por longitud + valor, nunca casteando a int:
 * `'0100' < '99'` es verdadero como cadena y falso como número.
 */
class RangoNumeracion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'voip_rangos_numeracion';

    protected $fillable = [
        'codigo', 'nombre', 'proposito', 'desde', 'hasta',
        'voip_perfil_extension_id', 'protegido', 'orden', 'activo',
        'es_plantilla_sistema',
    ];

    protected $casts = [
        'protegido'            => 'boolean',
        'activo'               => 'boolean',
        'es_plantilla_sistema' => 'boolean',
    ];

    public function perfil()
    {
        return $this->belongsTo(PerfilExtension::class, 'voip_perfil_extension_id');
    }

    public function extensiones()
    {
        return $this->hasMany(Extension::class, 'voip_rango_numeracion_id');
    }

    /** ¿Este número cae dentro del rango? Compara por longitud y luego por valor. */
    public function contiene(string $numero): bool
    {
        $numero = trim($numero);

        // Longitudes distintas = otro plan de numeración, no una comparación válida.
        if (strlen($numero) !== strlen($this->desde)) {
            return false;
        }

        return strcmp($numero, $this->desde) >= 0 && strcmp($numero, $this->hasta) <= 0;
    }

    /**
     * ¿Se traslapa con otro rango? Dos rangos de distinta longitud NO se traslapan:
     * pertenecen a planes de numeración diferentes.
     */
    public function seTraslapaCon(string $desde, string $hasta): bool
    {
        if (strlen($desde) !== strlen($this->desde)) {
            return false;
        }

        // Se traslapan salvo que uno termine antes de que el otro empiece.
        return ! (strcmp($hasta, $this->desde) < 0 || strcmp($desde, $this->hasta) > 0);
    }

    /** Cuántos números caben. Solo tiene sentido para rangos numéricos. */
    public function capacidad(): int
    {
        if (! ctype_digit($this->desde) || ! ctype_digit($this->hasta)) {
            return 0;
        }

        return max(0, ((int) $this->hasta) - ((int) $this->desde) + 1);
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }

    public function scopePorCodigo($q, string $codigo)
    {
        return $q->where('codigo', $codigo);
    }
}
