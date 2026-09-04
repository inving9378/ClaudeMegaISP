<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Activo digital o tecnológico — apartados VIII/IX: sistemas, plataformas,
 * software propio, servidores, bases de datos, dominios, licencias, etc.
 *
 * REGLA DE TITULARIDAD (enmienda de Irving, item #665): todo registro EXIGE
 * `titular`. Si no coincide (de forma laxa: contiene "MEGANET" o el
 * `razon_social` de la empresa) con la titularidad corporativa, el registro
 * nace/queda en `titularidad_a_regularizar` y dispara un `DcPendiente`
 * automático sobre el concepto del catálogo que corresponde a su `tipo` —
 * mismo mecanismo con el que Fase 0-2 ya alimentan el tablero de completitud,
 * sin inventar una segunda tubería de alertas.
 */
class DcActivoDigital extends Model
{
    use SoftDeletes;

    protected $table = 'dc_activos_digitales';

    protected $fillable = [
        'empresa_id', 'tipo', 'nombre', 'descripcion', 'proveedor', 'titular',
        'titularidad_estado', 'url', 'fecha_alta', 'vigencia_fin',
        'costo_periodico', 'periodicidad_costo', 'responsable_user_id', 'notas',
        'revocado_at', 'revocado_por_user_id',
    ];

    protected $casts = [
        'fecha_alta'       => 'date',
        'vigencia_fin'     => 'date',
        'costo_periodico'  => 'decimal:2',
        'revocado_at'      => 'datetime',
    ];

    public const TIPOS = [
        'sistema', 'plataforma', 'software_propio', 'servidor', 'base_datos',
        'app_movil', 'sitio_web', 'panel', 'licencia', 'respaldo', 'dominio',
        'correo_corporativo', 'red_social', 'plataforma_marketing',
    ];

    public const TITULARIDAD_REGULAR      = 'regular';
    public const TITULARIDAD_A_REGULARIZAR = 'titularidad_a_regularizar';

    /**
     * `tipo` → slug del concepto del catálogo que lo inventaría (CatalogoSeeder,
     * apartado VIII/IX). Es el mismo mapeo que ya vive en `config.filtros.tipo`
     * de cada concepto; se declara aquí en vez de consultarlo por JSON porque
     * es un mapa fijo de 14 entradas y evita depender del shape exacto del JSON
     * guardado en `dc_conceptos.config`.
     */
    private const CONCEPTO_POR_TIPO = [
        'sistema'              => 'sistemas-administrativos',
        'plataforma'           => 'plataformas-de-gestion',
        'software_propio'      => 'software-desarrollado-para-la-empresa',
        'servidor'             => 'servidores-fisicos-o-virtuales',
        'base_datos'           => 'bases-de-datos',
        'app_movil'            => 'aplicaciones-moviles',
        'sitio_web'            => 'sitios-web',
        'panel'                => 'paneles-de-administracion',
        'licencia'             => 'licencias-de-software',
        'respaldo'             => 'respaldos-de-informacion',
        'dominio'              => 'dominios-de-internet',
        'correo_corporativo'   => 'correos-electronicos-corporativos',
        'red_social'           => 'redes-sociales-corporativas',
        'plataforma_marketing' => 'plataformas-publicitarias-y-marketing',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $activo) {
            $activo->titularidad_estado = self::esTitularRegular($activo->titular, $activo->empresa_id)
                ? self::TITULARIDAD_REGULAR
                : self::TITULARIDAD_A_REGULARIZAR;
        });

        static::saved(function (self $activo) {
            if ($activo->titularidad_estado === self::TITULARIDAD_A_REGULARIZAR) {
                $activo->generarPendienteDeRegularizacion();
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function revocadoPor()
    {
        return $this->belongsTo(User::class, 'revocado_por_user_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /** Checklist de offboarding (item #761): activos digitales a cargo de un colaborador. */
    public function scopeDeResponsable($query, int $userId)
    {
        return $query->where('responsable_user_id', $userId);
    }

    public function estaRevocado(): bool
    {
        return $this->revocado_at !== null;
    }

    /**
     * Comparación laxa a propósito: en la práctica la razón social se captura
     * con variaciones de puntuación/mayúsculas ("Meganet Telecomunicaciones SA
     * de CV" vs "MEGANET Telecomunicaciones S.A. de C.V."). Se normaliza a solo
     * alfanuméricos y se acepta también que el titular simplemente contenga la
     * marca "MEGANET".
     */
    private static function esTitularRegular(?string $titular, ?int $empresaId): bool
    {
        if ($titular === null || trim($titular) === '') {
            return false;
        }

        $normaliza = fn (string $s) => mb_strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $s) ?? '');
        $t = $normaliza($titular);

        if (str_contains($t, 'MEGANET')) {
            return true;
        }

        $razonSocial = $empresaId ? DcEmpresa::find($empresaId)?->razon_social : null;
        if ($razonSocial === null) {
            return false;
        }

        return $t === $normaliza($razonSocial);
    }

    /**
     * Idempotente: si ya hay un pendiente abierto para el concepto que
     * corresponde a este `tipo`, no crea uno nuevo (evita spam cuando varios
     * activos digitales del mismo tipo necesitan regularizarse a la vez).
     */
    private function generarPendienteDeRegularizacion(): void
    {
        $slug = self::CONCEPTO_POR_TIPO[$this->tipo] ?? null;
        if ($slug === null) {
            return;
        }

        $concepto = DcConcepto::deEmpresa($this->empresa_id)->where('slug', $slug)->first();
        if ($concepto === null) {
            return;
        }

        $yaAbierto = DcPendiente::where('empresa_id', $this->empresa_id)
            ->where('concepto_id', $concepto->id)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->exists();

        if ($yaAbierto) {
            return;
        }

        DcPendiente::create([
            'empresa_id'           => $this->empresa_id,
            'concepto_id'          => $concepto->id,
            'responsable_user_id'  => $this->responsable_user_id,
            'estado'               => 'pendiente',
            'comentarios'          => "Titularidad a regularizar: \"{$this->nombre}\" está a nombre de "
                . "\"{$this->titular}\"; debe quedar a nombre de la razón social de la empresa.",
        ]);
    }
}
