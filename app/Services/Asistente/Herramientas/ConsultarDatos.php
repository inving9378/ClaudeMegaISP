<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\Datos\ConsultaRechazada;
use App\Services\Asistente\Datos\LectorBaseDatos;
use Illuminate\Support\Facades\Log;

/**
 * Consulta de lectura sobre cualquier información del sistema, y sobre los Excel adjuntos como si
 * fueran tablas (para cruzarlos). La revisión y el modo solo lectura viven en LectorBaseDatos.
 */
class ConsultarDatos extends Herramienta
{
    use UsaArchivos;

    public const MAX_FILAS = 200;

    public function nombre(): string
    {
        return 'consultar_datos';
    }

    public function descripcion(): string
    {
        return 'Consulta cualquier información del sistema, de cualquier módulo, con una consulta SQL de solo lectura (MySQL 8): '
            . 'una sola instrucción SELECT o WITH, sin comentarios ni variables. Para contar, sumar, agrupar, buscar o comparar lo que sea. '
            . 'Los Excel o CSV que te mandaron se usan como tablas si los pasas en «archivos»: la primera hoja es archivo_<id> y las demás '
            . 'archivo_<id>_<n> (columnas = sus identificadores, más _fila); así puedes cruzarlos con el sistema con JOIN. '
            . 'Devuelve máximo ' . self::MAX_FILAS . ' filas: para listas más grandes usa exportar_excel. Si no conoces las tablas o columnas, '
            . 'primero usa explorar_datos. Nombres que sean palabras reservadas van entre `acentos graves`. No se pueden consultar contraseñas ni llaves.';
    }

    public function permiso(): string|array|null
    {
        return ExplorarDatos::PERMISO;
    }

    public function maxCaracteresResultado(): ?int
    {
        return 24000;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sql'      => ['type' => 'string', 'description' => 'La consulta: una sola instrucción SELECT o WITH.'],
                'archivos' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Números de los Excel/CSV adjuntos que la consulta usa como tablas.'],
            ],
            'required' => ['sql'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $sql = (string) ($args['sql'] ?? '');
        try {
            $r = app(LectorBaseDatos::class)->consultar($sql, $this->hojasDe($user, $args['archivos'] ?? []), self::MAX_FILAS, 300);
        } catch (ConsultaRechazada $e) {
            return ['error' => $e->getMessage()];
        }
        Log::info('[JARVIS] consulta de datos', ['user' => $user->id, 'filas' => $r['total'], 'sql' => mb_substr($sql, 0, 1000)]);

        return array_filter([
            'columnas'         => $r['columnas'],
            'filas'            => $r['filas'],
            'filas_devueltas'  => $r['total'],
            'hay_mas'          => $r['truncado'] ?: null,
            'nota'             => $r['truncado'] ? 'Solo se muestran las primeras ' . self::MAX_FILAS . ' filas: para totales usa COUNT/SUM/GROUP BY; para el listado completo, exportar_excel.' : null,
        ], fn ($v) => $v !== null);
    }
}
