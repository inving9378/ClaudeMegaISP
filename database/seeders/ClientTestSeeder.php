<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClientTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Nota: este seeder antes invocaba UserAuthenticator::simulate() (backdoor de
     * sesión, código muerto) — removido en el item #221 del Circuito. El seeder
     * queda como no-op; no está registrado en ningún DatabaseSeeder.
     */
    public function run(): void
    {
        //
    }
}
