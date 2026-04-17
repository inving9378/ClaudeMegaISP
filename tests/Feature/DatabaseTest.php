<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    public function test_users_is_not_empty()
    {
        Schema::disableForeignKeyConstraints();

        // Obtener todos los usuarios
        $users = User::all();

        // Verificar que no hay usuarios en la base de datos
        $this->assertNotEmpty($users, 'La lista de usuarios no debe estar vacía.');
    }
}
