<?php

namespace Tests\Unit\Helpers;

use App\Http\Controllers\Module\Client\ClientController;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\BillingConfiguration;
use Mockery;
use App\Http\HelpersModule\module\client\ClientDatatableHelper;
use App\Models\Helper\Client as ClientRequest;

trait ClientTestHelper
{
    /**
     * Create a recurrent client for testing.
     *
     * @return Client
     */
    public function createClientRecurrent()
    {
        $request = ClientRequest::client1();
        $helperMock = Mockery::mock(ClientDatatableHelper::class);

        $controller = new ClientController($helperMock);
        $client = $controller->store($request);

        // Validaciones
        $this->assertInstanceOf(Client::class, $client);

        $clientMainInformacion = ClientMainInformation::where('client_id', $client->id)->first();
        $this->assertEquals($request['name'], $clientMainInformacion->name);
        $this->assertEquals($request['email'], $clientMainInformacion->email);
        $this->assertEquals($request['phone'], $clientMainInformacion->phone);
        $this->assertEquals($request['type_of_billing_id'], $clientMainInformacion->type_of_billing_id);

        $billingConfiguration = BillingConfiguration::where('client_id', $client->id)->first();
        $this->assertEquals($request['type_of_billing_id'], $billingConfiguration->type_billing_id);

        return $client;
    }

    public function createClientCustom()
    {
        $request = ClientRequest::client2();
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

        // Assert the client was created with the correct type of billing
        $this->assertEquals($request['type_of_billing_id'], $clientMainInformacion->type_of_billing_id);
        // Verificar que se creo un registro en billing_configurations con el client_id y que el type_of_billing sea el correcto
        $billingConfiguration = BillingConfiguration::where('client_id', $client->id)->first();
        $this->assertEquals($request['type_of_billing_id'], $billingConfiguration->type_billing_id);

        return $client;
    }

    /**
     * Simulate an authenticated user.
     *
     * @return void
     */
    private function simulateUserAutenticaded()
    {
        $userAuthenticator = new \App\Services\UserAuthenticator();
        $userAuthenticator->simulate();
    }
}
