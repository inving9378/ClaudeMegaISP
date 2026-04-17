<?php

namespace App\Http\Controllers\Utils;

use App\Rules\ValidateEmailImportClient;
use Carbon\Carbon;

class ComunConstantsController
{
    const URL_LOGO_DEFAULT = '/images/logo_meganet_oficial.png';
    const NUMBER_ACCOUNT_COMUN_USERS = '0100000000';
    const NUMBER_ACCOUNT_BUSINESS_USERS = '0200000000';
    const NUMBER_ACCOUNT_DEDICATED_SERVICES = '0300000000';

    const STATE_ACTIVE = 'Activo';
    const STATE_PENDING = 'Pendiente';
    const STATE_BLOCKED = 'Bloqueado';
    const STATE_INACTIVE = 'Inactivo';
    const STATE_NEW = 'Nuevo';
    const IS_NUMERICAL_TRUE = 1;
    const IS_NUMERICAL_FALSE = 0;
    const IS_TRUE = true;
    const IS_FALSE = false;
    const PAYMENT_TYPE_SERVICE_RECURRENTE = 'Pago recurrente';

    const STATUS_CLIENT_TO_FILTER = [
        "Activo" => "Activo",
        "Inactivo" => "Inactivo",
        "Nuevo" => "Nuevo",
        "Bloqueado" => "Bloqueado",
        "Cancelado" => "Cancelado"
    ];


    const MESES_EN_INGLES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const ALL_CLIENT_SERVICE = ['bundle_service', 'internet_service', 'voz_service', 'custom_service'];


    const IPV4_ASSIGNMENT_STATIC = 'IP Estatica';
    const IPV4_ASSIGNMENT_POOL_IP = 'Pool IP';

    // Router
    const IP_FIREWALL_NAT_WHIT_SLASH = '/ip/firewall/nat/'; //print  //add
    const IP_FIREWALL_RULES_WHIT_SLASH = '/ip/firewall/filter/'; //print
    const IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH = '/ip/firewall/address-list/'; //print
    const INTERFACE_PPPOE_CLIENT_WHIT_SLASH = '/interface/pppoe-client/'; //print
    const QUEUE_SIMPLE_WHIT_SLASH = '/queue/simple/'; //print
    const QUEUE_TYPE_WHIT_SLASH = '/queue/type/'; //print
    const IP_ARP_WHIT_SLASH = '/ip/arp/'; // add IP Arp
    const QUEUE_TYPE_WITH_SPACE = '/queue type '; // add Queue type
    const QUEUE_SIMPLE_WHIT_SPACE = '/queue simple '; //add simple queue
    const INTERCAFE_PPOE_CLIENT_WITH_SPACE = '/interface pppoe-client '; // add CLIENTE ppoe
    const IP_FIREWALL_ADDRESS_LIST_WITH_SPACE = '/ip firewall address-list '; //add cliete in address-list
    const IP_FIREWALL_FILTER_WITH_SPACE = '/ip firewall filter '; //add rules in firewall

    const PPP_SECRET_WHIT_SLASH = '/ppp/secret/'; //print

    const MODEL_CRM = 'App\Models\Crm';
    const MODEL_CRM_MAIN_INFORMATION = 'App\Models\CrmMainInformation';
    const MODEL_CLIENT = 'App\Models\Client';
    const MODEL_CLIENT_MAIN_INFORMATION = 'App\Models\ClientMainInformation';


    const RULES = [
        'Internet' => [
            'title' => 'required',
            'service_name' => 'required',
            'price' => 'required',
            'tax_include' => 'required',
            'tax' => 'required',
            'download_speed' => 'required',
            'upload_speed' => 'required',
            'guaranteed_speed_limit' => 'required',
            'priority' => 'required',
            'aggregation' => 'required',
            'burst' => 'required',
            'planes_internet_disponibles' => 'nullable|integer',
            'types_of_billing' => 'required',
            'prepaid_period' => 'required',
        ],
        'Custom' => [
            'title' => 'required',
            'service_name' => 'required',
            'price' => 'required',
            'tax_include' => 'required',
            'tax' => 'required',
            'types_of_billing' => 'required',
            'prepaid_period' => 'required',
            'priority' => 'required'
        ],
        'Voise' => [
            'title' => 'required',
            'service_name' => 'required',
            'price' => 'required',
            'type' => 'required',
            'tax_include' => 'required',
            'tax' => 'required',
            'types_of_billing' => 'required',
            'prepaid_period' => 'required',
            'priority' => 'required'
        ],
        'Client' => [
            'name' => 'required',
            'estado' => 'required|in:Nuevo,Activo,Inactivo,Bloqueado',
            'user' => 'unique:client_main_information,user',
        ],
        'Payment' => [
            'amount' => 'required',
            'receipt' => 'required',
            'client_id' => 'required'
        ],
        'Transaction' => [
            'type' => 'required',
            'price' => 'required',
            'client_id' => 'required',
            'category' => 'required',
            'date' => 'required',
        ],
        'ClientInternetService' => [
            'internet_id' => 'required',
            'estado' => 'required',
            'user' => 'required',
            'router_id'  => 'required',
            'ipv4_assignment' => 'required',
            'ipv4' => 'required_if:ipv4_assignment,IP Estatica',
            'ipv4_pool' => 'required_if:ipv4_assignment,Pool IP',
        ],
        'ClientVozService' => [
            'voz_id' => 'required',
            'estado' => 'required',
            'direction' => 'required',
        ],
        'ClientCustomService' => [],
        'ClientBundleService' => [],
        'Bundle' => [],
        'Partner' => [
            'name' => 'required'
        ],
        'NetworkIp' => [
            'ip' => 'required'
        ],
        'Network' => [],
        'ClientInvoice' => [],
    ];

    const ORDER_COLUMNS_MODULE_TO_EXPORT_EXCEL = [
        'Client' => [
            'client_id_old', //TODO Quitar despues de la primera importacion
            'name',
            'father_last_name',
            'mother_last_name',
            'mother_last_name',
            'email',
            'phone',
            'phone2',
            'state_id',
            'municipality_id',
            'colony_id',
        ],
        'Internet' => [
            'id_old', // TODO Eliminar Despues de la Primera Importacion
        ],
        'ClientPayment' => [
            'id_old',
            'client_id',
        ],
        'ClientTransaction' => [
            'client_id',
        ],
        'ClientInternetService' => [
            'client_id',
            'internet_id',
            'client_name',
            'password',
            'router_id',
            'ipv4_assignment',
            'ipv4',
            'ipv4_pool',
            'additional_ipv4',
            'ipv6',
            'start_date',
            'finish_date',
            'estado',
        ],
        'ClientVozService' => [
            'client_id',
            'voz_id',
            'phone',
            'password',
            'start_date',
            'finish_date',
            'estado',
            'voise_device',
            'direction'
        ],
        'ClientCustomService' => [
            'client_id',
            'custom_id',
            'user',
            'password',
            'router_id',
            'ipv4_assignment',
            'ipv4',
            'ipv4_pool',
            'additional_ipv4',
            'ipv6',
            'start_date',
            'finish_date',
            'estado',
            'pay_period'
        ],
        'ClientBundleService' => [
            'id_old',
            'client_id',

        ],
        'Bundle' => [
            'id_old',
        ],
        'Network' => [
            'id_old'
        ],
        'NetworkIp' => [
            'network_id'
        ],
        'ClientInvoice' => [
            'id_old',
            'client_id',
            'number',
            'total',
            'payment_date',
            'estado',
            'pay_up',
            'last_update',
            'use_of_transactions',
            'note',
            'memo',
            'payment',
            'is_sent',
            'delete_transactions',
            'added_by',
            'type',
        ]
    ];

    const LAST_COLUMNS_TO_IMPORT = [
        'Client' => [
            'estado',
            'phone3',
            'password_wifi'
        ],
        'ClientTransaction' => [
            'from_date',
            'to_date',
            'date_from_old',
            'date_to_old'
        ],
        'ClientInternetService' => [
            'client_bundle_service_id'
        ],
    ];


    const FORMAT_DATES = [
        "Y-m-d H:i:s",
        "Y-m-d H:i",
        "Y-m-d",
        "Y/m/d H:i:s",
        "Y/m/d H:i",
        "Y/m/d",
        "d-m-Y H:i:s",
        "d-m-Y H:i",
        "d-m-Y",
        "d/m/Y H:i:s",
        "d/m/Y H:i",
        "d/m/Y"
    ];

    const TEMPLATE_CONTRACTS = [
        1 => 'Contrato-Fijo-Revalidacion-12-Meses-recurrente',
        2 => 'Contrato-Fijo-En-Blanco',
    ];


    const DOCUMENT_TYPE_TEMPLATE_CLIENT = "Clientes";
    const DOCUMENT_TYPE_TEMPLATE_EMAIL = "Correos";

    /* Roles */
    const SELLER_ROLE = 'Vendedor';
    const CLIENT_ROLE = 'client';
    const SUPER_ADMINISTRADOR_CUSTOM_ROLE = 'Super Administrador';
    const SUPER_ADMIN_ROLE = 'super-administrator';
    const TECHNICAL_ROLE = 'TECNICO';
    const DEVELOPER_ROLE = 'DESARROLLADOR';
    const PARTNER_ROLE = 'Socio';


    const TYPE_SELLER_DISTRIBUITOR = 3;

    const STATUS_TASK_COLOR = [
        'ToDo' => 'rgb(173, 216, 230)',     // Azul claro (Light Blue)
        'InProgress' => 'rgb(255, 223, 186)', // Amarillo pastel (Light Peach)
        'Done' => 'rgb(144, 238, 144)',      // Verde claro (Light Green)
        'PostponedByClient' => 'rgb(255, 0, 0)', // Rojo
    ];

    //Inventory
    const INVENTORY_MOVEMENT_TYPE_ENTRADA = "Entrada";
    const INVENTORY_MOVEMENT_TYPE_SALIDA = "Salida";
    const STATUS_ITEM_NEW = "new";

    const INVENTORY_MOVEMENT_PENDING = "pending";
    const INVENTORY_MOVEMENT_ACCEPTED = "accepted";
    const INVENTORY_MOVEMENT_REJECTED = "rejected";

    const INVENTORY_MOVEMENT_STATUS = [
        'pending' => 'Pendiente',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
    ];

}
