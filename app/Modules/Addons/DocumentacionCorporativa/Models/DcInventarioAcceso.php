<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inventario de accesos — apartados VIII (credenciales de acceso) y XI
 * (cuentas bancarias, firmas, líneas de crédito, tokens). Registra QUÉ existe
 * y QUIÉN lo custodia; NUNCA el secreto.
 *
 * REGLA DE CREDENCIALES (enmienda de Irving, item #665): no hay columna de
 * contraseña/token/CLABE/tarjeta/llave privada, ni cifrada ni nullable. El
 * campo "Credencial" que ve el usuario es una CONSTANTE de código
 * (`getCredencialAttribute`), nunca datos de fila — así no hay nada que
 * capturar, migrar, exportar por error ni filtrar en un respaldo de MySQL.
 * Siempre va acompañado de su leyenda (`getCredencialLeyendaAttribute`),
 * porque el asterisco solo insinúa "la tenemos y no te la damos"; la leyenda
 * dice la verdad.
 */
class DcInventarioAcceso extends Model
{
    use SoftDeletes;

    protected $table = 'dc_inventario_accesos';

    /** OJO: ningún nombre de secreto aquí — es la garantía que prueba el test. */
    protected $fillable = [
        'empresa_id', 'tipo', 'institucion_o_sistema', 'identificador_publico',
        'titular', 'secreto_existe', 'custodio_user_id', 'ubicacion_resguardo',
        'fecha_ultima_revision', 'notas',
    ];

    protected $casts = [
        'secreto_existe'        => 'boolean',
        'fecha_ultima_revision' => 'date',
    ];

    /** Constante ASCII de 8 asteriscos. Nunca se lee de la base de datos. */
    public const CREDENCIAL_OCULTA = '********';

    protected $appends = ['credencial', 'credencial_leyenda'];

    public const TIPOS = [
        'cuenta_bancaria', 'linea_credito', 'cuenta_inversion', 'terminal_pv',
        'usuario_sistema', 'firma_autorizada', 'token',
    ];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function custodio()
    {
        return $this->belongsTo(User::class, 'custodio_user_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /** Máximo 4 caracteres, solo lo necesario para identificar el registro en una lista. */
    public function setIdentificadorPublicoAttribute(?string $value): void
    {
        $this->attributes['identificador_publico'] = $value === null
            ? null
            : mb_substr($value, -4);
    }

    public function getCredencialAttribute(): string
    {
        return self::CREDENCIAL_OCULTA;
    }

    public function getCredencialLeyendaAttribute(): string
    {
        $custodio = $this->relationLoaded('custodio')
            ? $this->custodio?->name
            : User::find($this->custodio_user_id)?->name;

        $revision = $this->fecha_ultima_revision?->format('d/m/Y') ?? 'sin registrar';

        return sprintf(
            "Credencial:  %s\n             No almacenada en el sistema por política de seguridad.\n"
            . "             Custodio: %s  ·  Resguardo: %s\n             Última revisión: %s",
            self::CREDENCIAL_OCULTA,
            $custodio ?: 'sin asignar',
            $this->ubicacion_resguardo ?: 'sin registrar',
            $revision
        );
    }
}
