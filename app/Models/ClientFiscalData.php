<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientFiscalData extends Model
{
    use SoftDeletes;

    protected $table = 'client_fiscal_data';

    protected $fillable = [
        'client_id',
        'facturacion_fiscal',
        'razon_social',
        'rfc',
        'codigo_postal_fiscal',
        'regimen_fiscal',
        'uso_cfdi',
        'correo_fiscal',
        'constancia_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'facturacion_fiscal' => 'boolean',
    ];

    // ─── Catálogos SAT (subconjunto para ISP persona física y moral) ──────────

    public static function catalogoRegimenFiscal(): array
    {
        return [
            '601' => 'General de Ley Personas Morales',
            '603' => 'Personas Morales con Fines no Lucrativos',
            '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
            '606' => 'Arrendamiento',
            '608' => 'Demás ingresos',
            '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
            '616' => 'Sin obligaciones fiscales',
            '621' => 'Incorporación Fiscal',
            '626' => 'Régimen Simplificado de Confianza (RESICO)',
        ];
    }

    public static function catalogoUsoCFDI(): array
    {
        return [
            'G01' => 'Adquisición de mercancias',
            'G02' => 'Devoluciones, descuentos o bonificaciones',
            'G03' => 'Gastos en general',
            'I01' => 'Construcciones',
            'I02' => 'Mobilario y equipo de oficina por inversiones',
            'I04' => 'Equipo de computo y accesorios',
            'I08' => 'Otra maquinaria y equipo',
            'D10' => 'Pagos por servicios educativos (colegiaturas)',
            'S01' => 'Sin efectos fiscales',
            'CP01' => 'Pagos',
            'CN01' => 'Nómina',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(\App\Modules\Core\Clientes\Models\Client::class);
    }

    // ─── Validación RFC ───────────────────────────────────────────────────────

    public static function validarRfc(string $rfc): bool
    {
        // Persona moral: 3 letras + 6 dígitos + 3 homoclave
        // Persona física: 4 letras + 6 dígitos + 3 homoclave
        return (bool) preg_match(
            '/^([A-ZÑ&]{3,4})(\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01]))([A-Z\d]{2}[A\d])$/',
            strtoupper(trim($rfc))
        );
    }

    public static function validarCodigoPostal(string $cp): bool
    {
        return (bool) preg_match('/^\d{5}$/', trim($cp));
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getRegimenFiscalNombreAttribute(): ?string
    {
        return static::catalogoRegimenFiscal()[$this->regimen_fiscal] ?? null;
    }

    public function getUsoCfdiNombreAttribute(): ?string
    {
        return static::catalogoUsoCFDI()[$this->uso_cfdi] ?? null;
    }
}
