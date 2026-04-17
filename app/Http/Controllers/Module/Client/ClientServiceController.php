<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Repository\ClientRepository;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientServiceController extends Controller
{
    protected $helper;
    protected $client_repository;

    public function __construct(ClientRepository $repository)
    {
        $this->client_repository = $repository;
    }

    public function hasService(Request $request, $clientId)
    {
        return $this->client_repository->hasService($clientId, $request->service);
    }

    public function canAddService(Request $request, $clientId)
    {
        return $this->client_repository->canAddService($clientId, $request->service);
    }
}
