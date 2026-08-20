<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Controllers\RoadmapController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * ENTREGA 1 — ejerce EL CAMINO QUE NADIE EJECUTA: el modo solo-lectura del panel.
 *
 * Por decisión de Irving (2026-08-19), `torre.config.view` NO se otorga a los ~15 roles de la
 * operación: la política de automatización del circuito de desarrollo no es información que
 * necesiten, y repartir un permiso «por si acaso» erosiona el modelo de permisos.
 *
 * El efecto secundario es que **nadie tiene `.view` sin `.edit`**, así que ese modo no lo ejerce
 * nadie — y un camino que nadie ejecuta se rompe en silencio. Es el destrabe otra vez, en versión
 * pequeña: algo que se ve sano porque nadie lo está mirando.
 *
 * Este comando crea un rol y un usuario TEMPORALES con sólo `torre.config.view`, ejerce los dos
 * endpoints de verdad y **hace ROLLBACK**. No deja nada en la base.
 *
 * Contrato = exit code: 0 el modo funciona, 1 se rompió.
 */
class VerificarSoloLecturaCommand extends Command
{
    protected $signature = 'circuito:verificar-solo-lectura';

    protected $description = 'Ejerce el modo solo-lectura del panel de la Torre (rol temporal + rollback). Exit 1 si se rompió.';

    public function handle(): int
    {
        $ok = true;

        try {
            DB::transaction(function () use (&$ok) {
                $rol = Role::create(['name' => '__tmp_solo_lectura_' . uniqid(), 'guard_name' => 'web']);
                $rol->givePermissionTo('torre.config.view');   // ADITIVO: sólo lectura, jamás edit

                $u = \App\Models\User::create([
                    'name'       => 'TMP solo lectura',
                    'login_user' => '__tmp_' . uniqid(),
                    'email'      => uniqid() . '@tmp.local',
                    'password'   => base64_encode('tmp'),
                ]);
                $u->assignRole($rol);
                auth()->login($u->fresh());

                $this->line('Usuario temporal con SOLO `torre.config.view`:');
                $this->line('  can view = ' . var_export($u->can('torre.config.view'), true)
                    . '   ·   can edit = ' . var_export($u->can('torre.config.edit'), true));

                $c = app(RoadmapController::class);

                // 1) El GET debe funcionar y decir que NO puede editar.
                $r = json_decode($c->torreConfig()->getContent(), true);
                $ok = $this->afirmar($ok, ($r['ok'] ?? false) === true, 'el GET responde 200');
                $ok = $this->afirmar($ok, ($r['puede_editar'] ?? true) === false,
                    'el GET dice puede_editar = false');
                $ok = $this->afirmar($ok, isset($r['politica']['matriz']),
                    'el GET trae la matriz completa (el panel se ve ENTERO, no recortado)');

                // 2) El POST debe NEGARSE. Es lo único que realmente protege: si esto pasa, la UI
                //    deshabilitada es decoración.
                $nego = false;
                try {
                    $c->torreConfigGuardar(new Request(['nivel_automatizacion' => 'autonomo']));
                } catch (AuthorizationException) {
                    $nego = true;
                }
                $ok = $this->afirmar($ok, $nego, 'el POST NIEGA (403) a quien sólo tiene `.view`');

                auth()->logout();
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__rollback__') {
                $this->error('Falló la verificación: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        if ($ok) {
            $this->info('✔ El modo solo-lectura funciona. Sigue dormido a propósito: nadie tiene '
                . '`torre.config.view` sin `torre.config.edit`.');

            return self::SUCCESS;
        }

        $this->error('✘ El modo solo-lectura se rompió. Es el camino que nadie ejerce, así que nada '
            . 'más lo habría delatado.');

        return self::FAILURE;
    }

    private function afirmar(bool $acc, bool $cond, string $que): bool
    {
        $this->line(($cond ? '  <fg=green>✔</>' : '  <fg=red>✘</>') . " {$que}");

        return $acc && $cond;
    }
}
