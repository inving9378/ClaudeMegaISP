<?php

namespace App\Modules\Core\Clientes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Buscador v2 del listado de Clientes (item roadmap #9990803).
 *
 * Punto único de entrada: aplicar(). Solo busca en los campos declarados en
 * config/clientes_busqueda.php (D4 — lista blanca), y solo dentro de los que
 * el llamador declare visibles, salvo prefijo explícito o modo ampliado.
 */
class ClienteSearchService
{
    public function tienePrefijoExplicito(string $termino): bool
    {
        return $this->extraerPrefijo($termino) !== null;
    }

    private function extraerPrefijo(string $termino): ?array
    {
        if (!preg_match('/^([a-z]+):(.+)$/i', trim($termino), $m)) {
            return null;
        }

        $prefijo = strtolower($m[1]);
        $valor = trim($m[2]);
        $mapa = config('clientes_busqueda.prefijos', []);

        if ($valor === '' || !isset($mapa[$prefijo])) {
            return null;
        }

        return ['campos' => $mapa[$prefijo], 'valor' => $valor];
    }

    /**
     * @param array $columnasVisibles Nombres de columna que el usuario tiene activas hoy.
     * @param bool  $ampliada         D3.3 — ignora columnasVisibles, busca en todo el mapa.
     */
    public function aplicar(Builder $query, string $termino, array $columnasVisibles, bool $ampliada = false): Builder
    {
        $termino = trim($termino);
        if ($termino === '') {
            return $query;
        }

        $todosLosCampos = config('clientes_busqueda.campos', []);
        $prefijo = $this->extraerPrefijo($termino);

        // D6 — mínimo de caracteres para disparar la consulta. Los prefijos explícitos
        // (sn:, tel:, ip:, id:, mac:) se saltan este mínimo, sin importar el largo del valor.
        if (!$prefijo) {
            $minCaracteres = (int) config('clientes_busqueda.min_caracteres', 3);
            if (mb_strlen($termino) < $minCaracteres) {
                return $query;
            }
        }

        if ($prefijo) {
            $claves = array_values(array_intersect($prefijo['campos'], array_keys($todosLosCampos)));
            $valor = $prefijo['valor'];

            return $query->where(function ($q) use ($claves, $valor, $todosLosCampos) {
                foreach ($claves as $clave) {
                    $this->aplicarCampo($q, $todosLosCampos[$clave], $valor);
                }
            });
        }

        // D4 — cruce contra la lista blanca. Lo que el front pida fuera del mapa se descarta en silencio.
        $claves = $ampliada
            ? array_keys($todosLosCampos)
            : array_values(array_intersect($columnasVisibles, array_keys($todosLosCampos)));

        if (empty($claves)) {
            // Nada de lo visible es buscable: 0 resultados (el front decide el mensaje, D3.3).
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($claves, $termino, $todosLosCampos) {
            foreach ($claves as $clave) {
                $this->aplicarCampo($q, $todosLosCampos[$clave], $termino);
            }
        });
    }

    private function aplicarCampo(Builder $query, array $campo, string $valor): void
    {
        $valorNormalizado = $this->normalizar($valor, $campo['normalizar'] ?? null);
        if ($valorNormalizado === '') {
            return;
        }

        $match = $campo['match'] ?? 'contiene';

        // Única excepción sin whereExists: columna de la propia tabla clients (hoy solo 'id').
        if (($campo['tabla'] ?? null) === 'clients' && empty($campo['correlacion'])) {
            if ($match === 'exacto') {
                if (!ctype_digit($valorNormalizado)) {
                    return;
                }
                $query->orWhere('clients.' . $campo['columna'], '=', (int) $valorNormalizado);
            } else {
                $query->orWhere('clients.' . $campo['columna'], 'like', $this->patron($valorNormalizado, $match));
            }
            return;
        }

        $tabla = $campo['tabla'];
        $correlacion = $campo['correlacion'];
        $columnaSql = !empty($campo['raw']) ? $campo['columna'] : "`{$tabla}`.`{$campo['columna']}`";
        $operador = $match === 'exacto' ? '=' : 'like';
        $valorSql = $match === 'exacto' ? $valorNormalizado : $this->patron($valorNormalizado, $match);

        $query->orWhereExists(function ($sub) use ($tabla, $correlacion, $columnaSql, $operador, $valorSql) {
            $sub->select(DB::raw(1))
                ->from($tabla)
                ->whereColumn("{$tabla}.{$correlacion}", 'clients.id')
                ->whereRaw("{$columnaSql} {$operador} ?", [$valorSql]);
        });
    }

    private function patron(string $valor, string $match): string
    {
        return match ($match) {
            'prefijo' => $valor . '%',
            'sufijo' => '%' . $valor,
            default => '%' . $valor . '%',
        };
    }

    private function normalizar(string $valor, ?string $tipo): string
    {
        return match ($tipo) {
            'telefono' => preg_replace('/[^0-9]/', '', $valor),
            'mac' => strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $valor)),
            'sn' => strtoupper(trim($valor)),
            default => $valor,
        };
    }
}
