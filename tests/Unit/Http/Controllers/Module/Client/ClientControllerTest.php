<?php

namespace Tests\Unit\Http\Controllers\Module\Client;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Helpers\ClientTestHelper;


class ClientControllerTest extends TestCase
{
    use RefreshDatabase, ClientTestHelper;

    /**
     * Test storing a new Client.
     *
     * @return void
     */
    public function testStore()
    {
        $this->simulateUserAutenticaded();
        $this->createClientRecurrent();
        $this->createClientCustom();
    }
}
