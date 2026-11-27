<?php

namespace App\Modules\Addons\CobranzaBlaster\Services;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A quién llama una campaña (27-nov, pedido de Irving): clientes Activos, Bloqueados y/o Inactivos,
 * si se quiere solo los de una zona o una caja, y si se quiere solo los que deben. Antes se elegía
 * por «tipo» y la zona salía de las tablas viejas districts/zones/box_zones, que en dev tienen una
 * sola fila de prueba (zona «54», caja «7»): por eso no aparecían las zonas ni los clientes.
 *
 * La caja del cliente es su nomenclatura `D1Z54C7:3` (distrito · zona · caja : salida). Se toma
 * de la fila de `nomenclatures` que el cliente ocupa (la que muestra la lista de Clientes) y, si no
 * hay, de la que apunta su ficha (`client_additional_information.box_nomenclator`). La nomenclatura
 * vieja (`box_nomenclator_old`, texto libre) no se puede leer: esos clientes salen sin filtrar por
 * zona, y al filtrar no.
 *
 * Se traen todos los candidatos en una consulta (unos 6,000) y lo demás se cuenta aquí: así los
 * números de los estados, de cada zona y de cada caja salen de la misma lista que se llama.
 */
class AudienciaCampana
{
    /** Estado de la ficha => cómo se dice en la pantalla. */
    public const ESTADOS = ['Activo' => 'Activos', 'Bloqueado' => 'Bloqueados', 'Inactivo' => 'Inactivos'];

    public const ESTADOS_POR_OMISION = ['Activo', 'Bloqueado'];

    /**
     * Filtros limpios. Lo que no se reconoce se descarta, nunca se ensancha: una zona mal escrita
     * no se vuelve «todas las zonas».
     *
     * @return array{estados: string[], zona: ?string, caja: ?string}
     */
    public static function normalizar(?array $a): array
    {
        $a = $a ?? [];
        $estados = array_values(array_intersect(array_keys(self::ESTADOS), (array) ($a['estados'] ?? self::ESTADOS_POR_OMISION)));

        $zona = self::clave($a['zona'] ?? null, false);
        $caja = self::clave($a['caja'] ?? null, true);
        if ($caja !== null) {
            $deLaCaja = self::zonaDeCaja($caja);
            if ($zona !== null && $zona !== $deLaCaja) {
                $caja = null;   // una caja de otra zona: se queda la zona
            } else {
                $zona = $deLaCaja;
            }
        }

        return ['estados' => $estados, 'zona' => $zona, 'caja' => $caja];
    }

    /**
     * «D1Z54C7:3» → ['zona' => 'D1Z54', 'caja' => 'D1Z54C7', 'distrito' => 1, 'letra' => 'Z', 'z' => 54, 'c' => 7].
     * Las de WiFi llevan W en vez de Z (D1W10C1): son otra zona aunque tengan el mismo número.
     *
     * @return array{zona: string, caja: string, distrito: int, letra: string, z: int, c: int}|null
     */
    public static function leerNomenclatura(?string $nombre): ?array
    {
        $base = (string) preg_replace('/\s+/', '', mb_strtoupper(explode(':', (string) $nombre)[0]));
        if (! preg_match('/^D(\d+)([ZW])(\d+)C(\d+)$/', $base, $m)) {
            return null;
        }
        [$d, $l, $z, $c] = [(int) $m[1], $m[2], (int) $m[3], (int) $m[4]];

        return ['zona' => "D{$d}{$l}{$z}", 'caja' => "D{$d}{$l}{$z}C{$c}", 'distrito' => $d, 'letra' => $l, 'z' => $z, 'c' => $c];
    }

    /** El estado de la ficha como llave de ESTADOS («activo », «ACTIVO» → «Activo»); null si es otro. */
    public static function estadoDe(?string $estado): ?string
    {
        $e = mb_convert_case(trim((string) $estado), MB_CASE_TITLE);

        return isset(self::ESTADOS[$e]) ? $e : null;
    }

    /**
     * Candidatos: una fila por cliente con estado Activo, Bloqueado o Inactivo, con su nomenclatura,
     * su teléfono y, si `$soloDeudores`, su adeudo vencido (sin adeudo no entra). Sin los que ya
     * tienen una llamada en esta campaña (en cobranza, una abierta: el que pagó puede volver a
     * entrar si vuelve a deber). Sin clientes borrados.
     *
     * La nomenclatura, en este orden:
     * 1. la fila de `nomenclatures` que el cliente ocupa (la de la lista de Clientes);
     * 2. la fila a la que apunta su ficha (`box_nomenclator` es el id) si nadie más la ocupa: muchas
     *    quedaron libres en el catálogo pero siguen siendo la caja del cliente (coinciden caja y
     *    salida con su nomenclatura vieja);
     * 3. si la ficha la escribió Nomenclatura ONU (`tipo_nomenclatura` lleno), `box_nomenclator` es el
     *    texto (D1Z54C7:3), no un id: se lee tal cual. Un número ahí no se toma por id.
     */
    public function candidatos(?CobranzaCampana $campana, bool $soloDeudores, int $diasVencimiento): Collection
    {
        $q = DB::table('clients as c')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'c.id')
            ->leftJoin('nomenclatures as n1', 'n1.client_id', '=', 'c.id')
            ->leftJoin('client_additional_information as cai', function ($j) {
                $j->on('cai.client_id', '=', 'c.id')->whereNull('cai.deleted_at');
            })
            ->leftJoin('nomenclatures as n2', function ($j) {
                $j->on('n2.id', '=', DB::raw("CAST(cai.box_nomenclator AS UNSIGNED)"))
                    ->whereNull('cai.tipo_nomenclatura')
                    ->whereRaw("cai.box_nomenclator REGEXP '^[0-9]+$'")
                    ->where(fn ($w) => $w->whereNull('n2.client_id')->orWhereColumn('n2.client_id', 'c.id'));
            })
            ->whereNull('c.deleted_at')
            ->whereIn('cmi.estado', array_keys(self::ESTADOS))
            ->select(
                'c.id as client_id',
                'cmi.estado',
                'cmi.phone',
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(cmi.name), ''), NULLIF(TRIM(cmi.father_last_name), ''), NULLIF(TRIM(cmi.mother_last_name), ''))) as nombre"),
                DB::raw('COALESCE(n1.name, n2.name, CASE WHEN cai.tipo_nomenclatura IS NOT NULL THEN cai.box_nomenclator END) as nomenclatura')
            )
            // Orden fijo: si un cliente tuviera dos filas, siempre gana la misma (revisión = activación).
            ->orderBy('c.id')->orderBy('n1.id')->orderBy('n2.id');

        if ($soloDeudores) {
            $adeudos = CobranzaCampanaService::facturasVencidas($diasVencimiento)
                ->groupBy('client_id')
                ->select('client_id', DB::raw('SUM(pending_balance) as monto'));
            $q->joinSub($adeudos, 'deuda', 'deuda.client_id', '=', 'c.id')->addSelect('deuda.monto');
        } else {
            $q->addSelect(DB::raw('0 as monto'));
        }

        if ($campana && $campana->exists) {
            $q->whereNotExists(function ($s) use ($campana, $soloDeudores) {
                $s->from('cobranza_llamadas')
                    ->whereColumn('cobranza_llamadas.client_id', 'c.id')
                    ->where('cobranza_llamadas.campana_id', $campana->id)
                    ->when($soloDeudores, fn ($w) => $w->whereNotIn('cobranza_llamadas.estado', ['pagada', 'excluida']));
            });
        }

        return self::unaPorCliente($q->get());
    }

    /**
     * Lo que ve la pantalla con estos filtros: a cuántos se llama, cuántos por estado, las zonas y
     * cajas que tienen clientes (con cuántos) y, con `$conLista`, la lista para revisarla.
     * `sin_caja`: los de esos estados sin nomenclatura nueva; al filtrar por zona no aparecen.
     *
     * Los números de cada estado no dependen de qué estados estén marcados (para saber cuántos
     * entrarían al marcarlo), y los de zonas y cajas no dependen de la zona o caja elegida.
     */
    public static function resumir(Collection $candidatos, array $filtros, bool $conLista): array
    {
        $f = self::normalizar($filtros);
        $porEstado = array_fill_keys(array_keys(self::ESTADOS), 0);
        $zonas = [];
        $clientes = [];
        $sinTelefono = 0;
        $sinCaja = 0;
        $monto = 0.0;

        foreach ($candidatos as $c) {
            $estado = self::estadoDe($c->estado);
            if ($estado === null) {
                continue;
            }
            $c->estado = $estado;
            $caja = self::leerNomenclatura($c->nomenclatura);
            $telefono = CobranzaLlamada::normalizarTelefono($c->phone);
            $enLugar = self::enLugar($caja, $f);

            if ($telefono !== null && $enLugar) {
                $porEstado[$c->estado]++;
            }
            if (! in_array($c->estado, $f['estados'], true)) {
                continue;
            }
            if ($caja === null && $telefono !== null) {
                $sinCaja++;   // con zona o sin ella: es a quien el filtro por zona no puede encontrar
            }
            if ($caja !== null && $telefono !== null) {
                $z = &$zonas[$caja['zona']];
                $z ??= ['clave' => $caja['zona'], 'distrito' => $caja['distrito'], 'letra' => $caja['letra'], 'zona' => $caja['z'], 'clientes' => 0, 'cajas' => []];
                $z['clientes']++;
                $z['cajas'][$caja['caja']] ??= ['clave' => $caja['caja'], 'caja' => $caja['c'], 'clientes' => 0];
                $z['cajas'][$caja['caja']]['clientes']++;
                unset($z);
            }
            if (! $enLugar) {
                continue;
            }
            if ($telefono === null) {
                $sinTelefono++;
                continue;
            }
            $monto += (float) $c->monto;
            if ($conLista) {
                $clientes[] = [
                    'id'           => (int) $c->client_id,
                    'nombre'       => trim((string) $c->nombre) !== '' ? trim((string) $c->nombre) : "Cliente {$c->client_id}",
                    'telefono'     => $telefono,
                    'estado'       => $c->estado,
                    'nomenclatura' => $caja ? trim((string) $c->nomenclatura) : null,
                    'monto'        => round((float) $c->monto, 2),
                ];
            }
        }

        $zonas = array_values($zonas);
        usort($zonas, fn ($a, $b) => [$a['distrito'], $a['letra'], $a['zona']] <=> [$b['distrito'], $b['letra'], $b['zona']]);
        foreach ($zonas as &$z) {
            $z['cajas'] = array_values($z['cajas']);
            usort($z['cajas'], fn ($a, $b) => $a['caja'] <=> $b['caja']);
        }
        unset($z);

        $r = [
            'filtros'      => $f,
            'clientes_n'   => array_sum(array_intersect_key($porEstado, array_flip($f['estados']))),
            'sin_telefono' => $sinTelefono,
            'sin_caja'     => $sinCaja,
            'monto'        => round($monto, 2),
            'por_estado'   => $porEstado,
            'zonas'        => $zonas,
        ];
        if ($conLista) {
            $r['clientes'] = $clientes;
        }

        return $r;
    }

    /** El mismo cliente con dos filas (dos nomenclaturas o dos fichas): se queda la primera. */
    private static function unaPorCliente(Collection $filas): Collection
    {
        return $filas->unique('client_id')->values();
    }

    private static function enLugar(?array $caja, array $f): bool
    {
        if ($f['caja'] !== null) {
            return $caja !== null && $caja['caja'] === $f['caja'];
        }
        if ($f['zona'] !== null) {
            return $caja !== null && $caja['zona'] === $f['zona'];
        }

        return true;
    }

    private static function clave(mixed $valor, bool $conCaja): ?string
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }
        $v = mb_strtoupper(trim((string) $valor));
        $patron = $conCaja ? '/^D(\d+)([ZW])(\d+)C(\d+)$/' : '/^D(\d+)([ZW])(\d+)$/';
        if (! preg_match($patron, $v, $m)) {
            return null;
        }

        return 'D' . (int) $m[1] . $m[2] . (int) $m[3] . ($conCaja ? 'C' . (int) $m[4] : '');
    }

    private static function zonaDeCaja(string $caja): string
    {
        return substr($caja, 0, strrpos($caja, 'C'));
    }
}
