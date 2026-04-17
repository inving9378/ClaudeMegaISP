<?php

namespace App\Observers;

use App\Http\Traits\RouterConnection;
use App\Models\ClientVozService;

class ClientVozServiceObserver
{
    use RouterConnection;


    public function created(ClientVozService $clientVozService)
    {

    }



}
