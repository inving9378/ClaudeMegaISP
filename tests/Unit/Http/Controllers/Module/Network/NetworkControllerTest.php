<?php

namespace Tests\Unit\Http\Controllers\Module\Network;

use App\Http\Controllers\Module\Client\ClientController;
use App\Http\Requests\module\client\ClientCreateRequest;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\User;
use App\Services\UserAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Http\HelpersModule\module\client\ClientDatatableHelper;
use Mockery;
use App\Models\Helper\Client as ClientRequest;


class NetworkControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test storing a new Client.
     *
     * @return void
     */
    public function testStore()
    {
        $this->simulateUserAutenticaded();
        $request = ClientRequest::client1();
        // Crear un mock del helper
        $helperMock = Mockery::mock(ClientDatatableHelper::class);
        // Instanciar el controlador con el mock
        $controller = new ClientController($helperMock);
        $client = $controller->store($request);

        // Assert new client was created successfully
        $this->assertInstanceOf(Client::class, $client);

        $clientMainInformacion = ClientMainInformation::where('client_id', $client->id)->first();
        // Assert the client properties
        $this->assertEquals($request['name'], $clientMainInformacion->name);
        $this->assertEquals($request['email'], $clientMainInformacion->email);
        $this->assertEquals($request['phone'], $clientMainInformacion->phone);
    }

    private function simulateUserAutenticaded()
    {
        $userAuthenticator = new UserAuthenticator();
        $userAuthenticator->simulate();
    }
}
