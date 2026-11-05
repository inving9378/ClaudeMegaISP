<?php

namespace App\Console\Commands\Scripts;

use App\Services\Security\PasswordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de filas `users` huérfanas: clientes de `client_main_information`
 * (con `user` y `password` poblados) que no tienen fila en `users`
 * (match por `users.login_user = cmi.user`).
 *
 * SEGURO POR DEFAULT (item #105): sin --apply sólo reporta, no escribe.
 * Idempotente: una segunda corrida no encuentra huérfanos que crear.
 * La fila nace SIN roles ni permisos (sólo identidad), con password bcrypt
 * vía PasswordService::make() a partir de la Contraseña WEB de la ficha.
 *
 * ⚠️ Escribir en producción exige bitácora PREVIA en docs/bitacora/ (CLAUDE.md).
 *
 * Uso:
 *   php artisan auth:backfill-client-users                  (dry-run)
 *   php artisan auth:backfill-client-users --muestra=10 --apply   (validar con 10)
 *   php artisan auth:backfill-client-users --apply
 */
class BackfillClientUsersCommand extends Command
{
    protected $signature = 'auth:backfill-client-users
                            {--apply : Escribe en la BD (sin esto sólo reporta)}
                            {--muestra=0 : Procesa sólo N clientes (0 = todos)}
                            {--chunk=200 : Tamaño de lote}';

    protected $description = 'Crea la fila users de clientes huérfanos (sin users.login_user = cmi.user); dry-run por defecto';

    public function handle(): int
    {
        $apply   = (bool) $this->option('apply');
        $muestra = max(0, (int) $this->option('muestra'));
        $chunk   = max(1, (int) $this->option('chunk'));

        $base = fn () => DB::table('client_main_information as c')
            ->leftJoin('users as u', 'u.login_user', '=', 'c.user')
            ->whereNull('u.id')
            ->whereNull('c.deleted_at')
            ->whereNotNull('c.user')->where('c.user', '!=', '')
            ->select(['c.id', 'c.client_id', 'c.user', 'c.password', 'c.name', 'c.father_last_name',
                'c.mother_last_name', 'c.email', 'c.phone']);

        $total = $base()->count();
        $sinPassword = $base()->where(fn ($q) => $q->whereNull('c.password')->orWhere('c.password', ''))->count();

        $this->info(($apply ? '' : '[DRY-RUN] ') . "Huérfanos: {$total} (sin contraseña, se saltan: {$sinPassword})"
            . ($muestra ? " — muestra de {$muestra}" : ''));

        $creados = 0;
        $saltados = 0;
        $visto = 0;

        $base()->orderBy('c.id')->chunk($chunk, function ($rows) use (&$creados, &$saltados, &$visto, $apply, $muestra) {
            foreach ($rows as $r) {
                if ($muestra && $visto >= $muestra) {
                    return false;
                }
                $visto++;

                if ($r->password === null || $r->password === '') {
                    $saltados++;
                    continue;
                }

                if ($apply) {
                    $plain = $r->password;
                    $now = now();
                    DB::table('users')->insertOrIgnore([
                        'name'              => $r->name,
                        'father_last_name'  => $r->father_last_name,
                        'mother_last_name'  => $r->mother_last_name,
                        'email'             => $r->email ?: null,
                        'login_user'        => $r->user,
                        'password'          => PasswordService::isHashed($plain) ? $plain : PasswordService::make($plain),
                        'phone'             => $r->phone,
                        'client_id'         => $r->client_id,
                        'active'            => 1,
                        'estado'            => 'activo',
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                }
                $creados++;
            }
        });

        $this->table(['Creados' . ($apply ? '' : ' (simulado)'), 'Saltados (sin contraseña)'], [[$creados, $saltados]]);
        if (! $apply) {
            $this->warn('DRY-RUN: no se escribió nada. Usa --apply (primero con --muestra=10).');
        }

        return self::SUCCESS;
    }
}
