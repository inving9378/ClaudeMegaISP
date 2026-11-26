<?php

namespace App\Modules\Addons\CobranzaBlaster\Models;

use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CobranzaLlamada extends Model
{
    protected $table = 'cobranza_llamadas';

    protected $fillable = [
        'campana_id', 'client_id', 'telefono', 'intentos', 'estado',
        'ultimo_intento_at', 'proximo_intento_at',
        'ami_channel', 'ami_uniqueid', 'monto_vencido',
    ];

    protected $casts = [
        'monto_vencido'       => 'decimal:2',
        'intentos'            => 'integer',
        'ultimo_intento_at'   => 'datetime',
        'proximo_intento_at'  => 'datetime',
    ];

    /**
     * El número que se marca: 10 dígitos nacionales, o null si no hay uno (25-nov).
     *
     * En la ficha del cliente a veces vienen dos números en el mismo campo, guiones,
     * espacios, +52 o el 044 de antes. Se toma el primero que sea un número de 10
     * dígitos. Antes se guardaba el texto tal cual: con dos números no cabía en la
     * columna y la activación de la campaña tronaba, y lo que sí cabía se marcaba con
     * espacios dentro. Pura: probada en tests/Unit.
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        // Sin la extensión del final («ext. 12», «x 12»): no se puede marcar por la troncal.
        $texto = trim(preg_replace('/\s*(?:ext|extensi[oó]n|x)\.?\s*\d+\s*$/iu', '', (string) $telefono));
        if ($texto === '') {
            return null;
        }

        // Un solo número escrito como sea («55  1234  5678», «(55) 1234-5678»).
        if (($n = self::diezDigitos(preg_replace('/\D/', '', $texto))) !== null) {
            return $n;
        }

        // Separadores claros entre dos números: coma, punto y coma, diagonal, barra,
        // salto de línea, dos o más espacios, « y » / « o ».
        foreach (preg_split('/[,;\/|\r\n]+|\s{2,}|\s+(?:y|o|ó)\s+/iu', $texto) ?: [] as $parte) {
            if (($n = self::diezDigitos(preg_replace('/\D/', '', $parte))) !== null) {
                return $n;
            }
        }

        // Pegados con un solo espacio («5512345678 5587654321»): el primero.
        $digitos = preg_replace('/\D/', '', $texto);
        if (strlen($digitos) >= 20 && strlen($digitos) % 10 === 0) {
            return substr($digitos, 0, 10);
        }

        // Uno incompleto y otro bueno con un espacio en medio: el bueno.
        foreach (preg_split('/\s+/', $texto) ?: [] as $pedazo) {
            if (($n = self::diezDigitos(preg_replace('/\D/', '', $pedazo))) !== null) {
                return $n;
            }
        }

        return null;
    }

    private static function diezDigitos(string $d): ?string
    {
        return match (true) {
            strlen($d) === 10 => $d,
            strlen($d) === 12 && (str_starts_with($d, '52') || str_starts_with($d, '01')) => substr($d, 2),
            strlen($d) === 13 && (str_starts_with($d, '521') || str_starts_with($d, '044') || str_starts_with($d, '045')) => substr($d, 3),
            default => null,
        };
    }

    public function campana()
    {
        return $this->belongsTo(CobranzaCampana::class, 'campana_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function eventos()
    {
        return $this->hasMany(CobranzaLlamadaEvento::class, 'llamada_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente')
            ->where('proximo_intento_at', '<=', now());
    }

    public function scopeParaReintentar($query)
    {
        return $query->whereIn('estado', ['no_contesto', 'ocupado'])
            ->whereColumn('intentos', '<', DB::raw(
                '(SELECT max_intentos FROM cobranza_campanas WHERE id = cobranza_llamadas.campana_id)'
            ));
    }
}
