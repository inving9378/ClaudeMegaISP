<?php

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * EL CANDADO DE LA BASE DE PRUEBAS (incidente del 2026-08-25).
 *
 * ── QUÉ PASÓ ────────────────────────────────────────────────────────────────────────────────────
 *
 * `phpunit.xml` declaraba `DB_DATABASE=megaisp` — la base de DEV, la misma que usa la app — y
 * `Tests\TestCase::setUp()` corre `migrate:fresh --seed`. Es decir: correr la suite borraba dev
 * entera, y era la configuración por defecto del repo. El 2026-08-25 a las 18:14 una terminal del
 * circuito corrió phpunit mientras trabajaba el item #171 y `megaisp` quedó en **0 tablas**: el
 * `migrate` de vuelta no reconstruyó nada porque el guardrail de migraciones (fail-closed,
 * commiteado minutos antes en ese mismo worktree) no podía leer la tabla `migrations`… que el
 * propio `migrate:fresh` acababa de borrar.
 *
 * ── POR QUÉ UN CANDADO Y NO SÓLO CORREGIR EL XML ────────────────────────────────────────────────
 *
 * Cambiar `phpunit.xml` arregla el caso de hoy y se cae solo el día que alguien lo edite, restaure
 * un `phpunit.xml` viejo, exporte `DB_DATABASE` en su shell o corra la suite con otro archivo de
 * configuración. El XML es la CONFIGURACIÓN; esto es el CANDADO. Misma forma que el resto del
 * circuito: la regla vive UNA vez, y se aplica en el único punto por el que pasa todo test que
 * llega a tocar la base (`Tests\CreatesApplication::createApplication()`), no repartida en cada
 * clase base — que es justo como se cayó la regla que sí existía (estaba en la memoria de CC, y
 * las seis terminales no leen esa memoria).
 *
 * ── LA REGLA ────────────────────────────────────────────────────────────────────────────────────
 *
 * El nombre de la base contra la que corre la suite tiene que terminar en `_test` (o ser SQLite en
 * memoria, que no puede destruir nada). **No hay lista de excepciones a propósito:** una lista de
 * excepciones se llena, y basta una entrada mal puesta para volver al 18:14.
 *
 * Si el nombre no se puede determinar, se BLOQUEA. No saber contra qué base se va a correr
 * `migrate:fresh --seed` es exactamente el caso peligroso, no uno benigno.
 */
final class GuardBaseDePruebas
{
    /** Lo que hace que una base sea de pruebas: su nombre lo dice. */
    public const SUFIJO_OBLIGATORIO = '_test';

    /** SQLite en memoria no tiene nada que destruir; es el único nombre que pasa sin sufijo. */
    public const EN_MEMORIA = ':memory:';

    /**
     * ¿Este nombre de base es seguro para una suite que corre `migrate:fresh`?
     *
     * `null`/vacío devuelve false a propósito (fail-closed): la ausencia de dato NO es permiso.
     */
    public static function esBaseDePruebas(?string $nombre): bool
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return false;
        }

        if ($nombre === self::EN_MEMORIA) {
            return true;
        }

        return str_ends_with($nombre, self::SUFIJO_OBLIGATORIO);
    }

    /**
     * Bloquea si el nombre no es de una base de pruebas. Lanza — no `exit` — para que el fallo se
     * vea como un error de la suite y no como una corrida verde a medias.
     */
    public static function verificar(?string $nombre): void
    {
        if (self::esBaseDePruebas($nombre)) {
            return;
        }

        throw new RuntimeException(self::mensaje($nombre));
    }

    /**
     * El nombre DEFINITIVO sale de la app ya arrancada (`config`), no de `getenv`: para cuando esto
     * corre, phpunit ya aplicó su bloque `<php><env>`, el `.env` ya se leyó y cualquier override
     * ya está resuelto. Preguntarle al entorno daría una respuesta distinta a la que usará el
     * migrador, y una comprobación que mira otra cosa que el peligro no es una comprobación.
     */
    public static function verificarApp(Application $app): void
    {
        $config = $app->make('config');
        $conexion = (string) $config->get('database.default');

        self::verificar($config->get("database.connections.{$conexion}.database"));
    }

    private static function mensaje(?string $nombre): string
    {
        $visto = trim((string) $nombre) === '' ? '(no se pudo determinar)' : $nombre;

        return implode(PHP_EOL, [
            '',
            '  ╔══════════════════════════════════════════════════════════════════════════════╗',
            '  ║  SUITE DETENIDA: la base configurada NO es una base de pruebas.              ║',
            '  ╚══════════════════════════════════════════════════════════════════════════════╝',
            '',
            "  Base configurada: {$visto}",
            '  Requisito:        el nombre debe terminar en "'.self::SUFIJO_OBLIGATORIO.'" (o ser '.self::EN_MEMORIA.').',
            '',
            '  Tests\TestCase::setUp() corre `migrate:fresh --seed`, y RefreshDatabase hace lo',
            '  mismo: correr la suite contra la base de la app la deja en cero. Así se perdió',
            '  `megaisp` el 2026-08-25 18:14.',
            '',
            '  Qué hacer:',
            '    1. `phpunit.xml` debe declarar <env name="DB_DATABASE" value="megaisp_test"/>.',
            '    2. Si la base no existe (hace falta root de MySQL, una sola vez):',
            '         CREATE DATABASE megaisp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
            '         GRANT ALL PRIVILEGES ON megaisp_test.* TO \'megaisp_user\'@\'localhost\';',
            '',
            '  Este candado NO se salta editando el XML: vive en tests/GuardBaseDePruebas.php.',
            '',
        ]);
    }
}
