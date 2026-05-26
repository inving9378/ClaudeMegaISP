<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientMainInformation;

class WhatsAppContactMatcherService
{
    /**
     * Buscar cliente por número WhatsApp.
     *
     * En megaisp el teléfono vive en client_main_information.{phone,phone2,phone3}.
     * El número entrante de WhatsApp viene como E.164 sin "+" (ej 5215512345678),
     * los teléfonos en la BD pueden venir con formatos heterogéneos (con guiones,
     * con prefijo o sin, con espacios) — se compara contra los últimos 10 dígitos.
     *
     * @return array{client: ?Client, seller_id: ?int}
     */
    public function match(string $contactNumber): array
    {
        $normalized = preg_replace('/[^0-9]/', '', $contactNumber);
        $last10     = substr($normalized, -10);

        if (strlen($last10) < 10) {
            return ['client' => null, 'seller_id' => null];
        }

        $main = ClientMainInformation::whereRaw(
            "REGEXP_REPLACE(IFNULL(phone, ''), '[^0-9]', '') LIKE ?",
            ['%' . $last10]
        )->orWhereRaw(
            "REGEXP_REPLACE(IFNULL(phone2, ''), '[^0-9]', '') LIKE ?",
            ['%' . $last10]
        )->orWhereRaw(
            "REGEXP_REPLACE(IFNULL(phone3, ''), '[^0-9]', '') LIKE ?",
            ['%' . $last10]
        )->first();

        if (!$main) {
            return ['client' => null, 'seller_id' => null];
        }

        $client = Client::find($main->client_id);

        return [
            'client'    => $client,
            'seller_id' => $main->seller_id,
        ];
    }
}
