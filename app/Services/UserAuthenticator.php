<?php

namespace App\Services;

use App\Models\User;

class UserAuthenticator
{
    /**
     * Simulates an authenticated user.
     *
     * @return User
     */
    public function simulate(): User
    {
        // Create a fake user
        $fakeUser = User::factory()->create();

        // Simulate authentication for the fake user
        auth()->login($fakeUser);

        return $fakeUser;
    }
}
