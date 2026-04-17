<?php

namespace App\Models\Helper;

use App\Http\Repository\ReceiptRepository;
use App\Http\Requests\module\client\ClientCreateRequest;
use App\Http\Requests\module\client\ClientPaymentRequest;
use App\Models\TypeBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Client
{
    public static function client1(): ClientCreateRequest
    {
        // Create a new request
        $request = new ClientCreateRequest();

        // Mock expected request data
        $request_data = [
            'name' => mt_rand(1000, 9999) . 'Test Name Recurrent',
            'father_last_name' => 'Test Father Last Name Recurrent',
            'mother_last_name' => 'Test Mother Last Name Recurrent',
            'colony_id' => 1,
            'user' => 'test_user_recurrent' . mt_rand(1000, 9999),
            'email' => 'test'. mt_rand(1000, 9999) .'@mail.com',
            'phone' => 612312312,
            'power_dbm' => 10,
            'nif_pasaport' => '123456789',
            'password' => 'securepassword',
            'type_of_billing_id' => TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT,
        ];

        return $request->merge($request_data);
    }
    public static function client2(): ClientCreateRequest
    {
        // Create a new request
        $request = new ClientCreateRequest();

        // Mock expected request data
        $request_data = [
            'name' =>  mt_rand(1000, 9999) .'Test2 Name Custom',
            'father_last_name' => 'Test2 Father Last Name Custom',
            'mother_last_name' => 'Test2 Mother Last Name Custom',
            'colony_id' => 1,
            'user' => 'test'. mt_rand(1000, 9999) .'test_user_custom',
            'email' => 'test'. mt_rand(1000, 9999) .'test2@mail.com',
            'phone' => 612312312,
            'power_dbm' => 10,
            'nif_pasaport' => '123456789',
            'password' => 'securepassword',
            'type_of_billing_id' => TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM,
        ];

        return $request->merge($request_data);
    }

    public static function paymentClient1CubreElCostoDeLosServicios(): ClientPaymentRequest
    {
        // Create a new request
        $request = new ClientPaymentRequest();
        // Mock expected request data
        $request_data = [
            'payment_method_id' => 1,
            'amount' => 520,
            'payment_period' => 'Periodo 1',
            'receipt' => (new ReceiptRepository())->getReceipt(),
        ];
        return $request->merge($request_data);
    }

    public static function paymentClient1($amount): ClientPaymentRequest {
        // Create a new request
        $request = new ClientPaymentRequest();
        // Mock expected request data
        $request_data = [
            'payment_method_id' => 1,
            'amount' => $amount,
            'payment_period' => 'Periodo 1',
            'receipt' => (new ReceiptRepository())->getReceipt(),
        ];
        return $request->merge($request_data);
    }

    public static function requestPackage()
    {
        //Paquete ESTUDIANTE+
        $request_data = [
            "bundle_discount_percent" => null,
            "bundle_start_date_discount" => null,
            "bundle_end_date_discount" => null,
            "bundle_discount_message" => null,
            "bundle_contract_start_date" => "2024-12-25T11:36",
            "bundle_id" => "2",
            "bundle_description" => "ESTUDIANTE+",
            "bundle_automatic_renewal" => true,
            "bundle_price" => "520",
            "bundle_estado" => "Pendiente",
            "bundle_pay_period" => "Periodo 1",
            "bundle_discount" => false,
            "plan_internet_ipv4_pool_40" => null,
            "plan_internet_additional_ipv4_40" => null,
            "plan_internet_ipv4_40" => "1385", //10.10.8.3
            "plan_internet_ipv4_assignment_40" => "IP Estatica",
            "plan_internet_client_name_40" => "client_test_recurrent",
            "plan_internet_password_40" => "securepassword",
            "plan_internet_router_id_40" => "2",
            "plan_internet_ipv6_40" => null,
            "plan_internet_delegated_ipv6_40" => null,
            "plan_internet_mac_40" => null,
            "plan_internet_portid_40" => null,
            "plan_voz_phone_2" => "55555555",
            "plan_voz_password_2" => "2tdS3016",
            "plan_voz_voise_device_2" => "VoIP",
            "plan_voz_direction_2" => "Salientes",
            "plan_custom_ipv4_3" => null,
            "plan_custom_additional_ipv4_3" => null,
            "plan_custom_ipv4_pool_3" => null,
            "plan_custom_ipv4_assignment_3" => null,
            "plan_custom_hidden_3" => null,
            "plan_custom_service_name_3" => "MovieNet",
            "plan_custom_price_3" => 125,
            "plan_custom_user_3" => null,
            "plan_custom_password_3" => null,
            "plan_custom_router_id_3" => null,
            "plan_custom_mac_3" => null,
            "plan_custom_serial_number_3" => null,
            "plan_custom_bandwidth_3" => "5",
            "plan_custom_cost_activation_3" => null,
            "plan_custom_cost_instalation_3" => null,
        ];
        $request = new Request($request_data);
        return $request;
    }
}
