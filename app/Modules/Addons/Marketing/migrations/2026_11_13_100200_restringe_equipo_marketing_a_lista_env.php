<?php

use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Marketing IA 360 · Fase 1 — §2.6.2 (condición de Henry para aprobar): al aplicar la versión, en NINGÚN momento
 * entra a Marketing nadie fuera de la lista, sin comando posterior.
 *
 * Lista en el .env del servidor (no en el repo): MARKETING_EQUIPO_LOGINS=<login_user>,<login_user>. Vacía o ausente →
 * nadie entra. Sobre la empresa principal (Meganet):
 *  - desactiva (`activo = false`, NO borra: reversible) toda fila de `marketing_empresa_usuario` cuyo usuario no esté
 *    en la lista — incluidos los «admins por rol» que dejó la migración 2026_11_12_100300;
 *  - cada login de la lista que exista → fila `admin` activa + givePermissionTo (aditivo) de los permisos de
 *    Marketing (los mismos que `marketing:equipo-fijar`, sin `manage-integrations`); login inexistente → se ignora y
 *    se avisa en la salida;
 *  - escribe la MARCA `marketing_settings.equipo_fijado_at` (sin ella el middleware niega a todos) y `activity_log`
 *    (`event=equipo_restringido`, solo conteos, sin logins).
 * Idempotente. `down()` borra la marca (con eso nadie entra) y no reactiva filas.
 * Toda la lógica vive en Support\EquipoMarketing (la comparte el comando). Sin ids; sin logins en el repo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $resumen = EquipoMarketing::restringirALista(EquipoMarketing::loginsDeLaLista());

        $this->avisar(sprintf(
            'Marketing: equipo restringido a %s (en la lista: %d, encontrados: %d, filas desactivadas: %d).',
            EquipoMarketing::VARIABLE_LISTA,
            $resumen['en_lista'],
            $resumen['encontrados'],
            $resumen['filas_desactivadas']
        ));

        if ($resumen['en_lista'] === 0) {
            $this->avisar('Marketing: la lista está vacía o no existe → entran solo los roles de marketing.roles_con_acceso (acceso por rol desde el 2026-11-14).');
        }

        foreach ($resumen['ignorados'] as $login) {
            $this->avisar("Marketing: «{$login}» está en la lista pero no existe ningún usuario con ese login_user; se ignoró.");
        }
    }

    public function down(): void
    {
        EquipoMarketing::quitarMarca();
    }

    private function avisar(string $texto): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            (new ConsoleOutput())->writeln("  <comment>{$texto}</comment>");
        }
    }
};
