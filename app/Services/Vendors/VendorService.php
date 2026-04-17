<?php

namespace App\Services\Vendors;

use App\Http\Repository\ClientMainInformationRepository;

class VendorService
{
    public function getSalesBySeller($sellerId)
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        return $clientMainInformationRepository->getClientsBySellerId($sellerId);
    }
}
