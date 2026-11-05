<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\User;
use Database\Seeders\UniPruebaSeeder;
use Tests\TestCase;

class UniPruebaPerfilesTest extends TestCase
{
    public function test_siembra_perfiles_uni_prueba(): void
    {
        $colabs = (new UniPruebaSeeder())->perfiles();

        $this->assertCount(9, $colabs);
        $this->assertSame(
            9,
            User::where('login_user', 'like', 'UNI-PRUEBA-%')->where('name', 'like', 'UNI-PRUEBA %')->count()
        );
        $this->assertSame(2, $colabs['supervisor']->subordinados()->count());
        $this->assertTrue($colabs['tecnico1']->user->hasRole('TECNICO'));
        $this->assertTrue($colabs['mostrador']->user->hasRole('Mostrador'));
        $this->assertTrue($colabs['direccion']->user->hasRole('DESARROLLADOR'));

        // Idempotente
        (new UniPruebaSeeder())->perfiles();
        $this->assertSame(9, User::where('login_user', 'like', 'UNI-PRUEBA-%')->count());
    }
}
