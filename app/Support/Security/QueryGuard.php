<?php

namespace App\Support\Security;

use Illuminate\Database\Eloquent\Model;

/**
 * Validador de identificadores, modelos y scopes para consultas dinámicas.
 *
 * Las consultas que toman el nombre del MODELO, de la COLUMNA o del SCOPE desde
 * el request (p.ej. SearchModelController) son vulnerables a:
 *   - Inyección de clase / invocación de métodos estáticos arbitrarios:
 *       $model::$scope()  /  new $model  → cualquier clase y método del sistema.
 *   - Inyección SQL por NOMBRE DE COLUMNA: los VALORES se parametrizan (PDO bind),
 *     pero los IDENTIFICADORES interpolados en selectRaw()/where(col) NO.
 *
 * Esta clase centraliza la validación y FALLA CERRADO (abort 422) ante cualquier
 * entrada que no calce con una forma segura conocida. Úsese en todo controlador
 * que arme queries a partir de nombres recibidos del cliente.
 */
class QueryGuard
{
    /** Namespaces raíz permitidos para un modelo recibido del request. */
    private const ALLOWED_MODEL_PREFIXES = [
        'App\\Models\\',
        'App\\Modules\\',
    ];

    /**
     * Valida que $model sea un modelo Eloquent permitido y existente.
     * Devuelve el FQCN normalizado (sin backslash inicial).
     */
    public static function model(?string $model): string
    {
        $model = ltrim((string) $model, '\\');

        $allowedNamespace = false;
        foreach (self::ALLOWED_MODEL_PREFIXES as $prefix) {
            if (str_starts_with($model, $prefix)) {
                $allowedNamespace = true;
                break;
            }
        }

        // Solo letras, números, backslash y guion bajo: corta `(`, `;`, espacios,
        // `::`, etc. usados para invocar métodos o anidar expresiones.
        if (
            !$allowedNamespace
            || !preg_match('/^[A-Za-z0-9_\\\\]+$/', $model)
            || !class_exists($model)
            || !is_subclass_of($model, Model::class)
        ) {
            abort(422, 'Modelo no permitido.');
        }

        return $model;
    }

    /**
     * Valida un identificador SQL simple (nombre de columna): ^[A-Za-z0-9_]+$.
     *
     * @param bool $allowNull si true, un valor vacío/null se devuelve como null
     *                        sin abortar (un valor ausente no puede inyectar).
     */
    public static function identifier(?string $name, bool $allowNull = false): ?string
    {
        if ($name === null || $name === '') {
            if ($allowNull) {
                return null;
            }
            abort(422, 'Identificador requerido.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            abort(422, 'Identificador inválido.');
        }
        return $name;
    }

    /**
     * Valida un nombre de relación Eloquent (permite notación punto: rel.columna).
     */
    public static function relation(?string $name, bool $allowNull = false): ?string
    {
        if ($name === null || $name === '') {
            if ($allowNull) {
                return null;
            }
            abort(422, 'Relación requerida.');
        }
        if (!preg_match('/^[A-Za-z0-9_.]+$/', $name)) {
            abort(422, 'Relación inválida.');
        }
        return $name;
    }

    /**
     * Valida un nombre de scope local contra el modelo: debe existir el método
     * scope{Nombre}() (así Laravel resuelve $model::nombre()).
     */
    public static function scope(string $model, ?string $scope, bool $allowNull = true): ?string
    {
        if ($scope === null || $scope === '') {
            if ($allowNull) {
                return null;
            }
            abort(422, 'Scope requerido.');
        }
        if (
            !preg_match('/^[A-Za-z0-9_]+$/', $scope)
            || !method_exists($model, 'scope' . ucfirst($scope))
        ) {
            abort(422, 'Scope no permitido.');
        }
        return $scope;
    }
}
