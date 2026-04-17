<?php

namespace App\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\BundleRepository;
use App\Http\Repository\ClientRepository;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Models\Bundle;
use App\Models\Client;
use App\Models\ClientInternetService;
use App\Models\ClientMainInformation;
use App\Models\ClientUser;
use App\Models\NetworkIp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreatePackageService
{
    public function __construct() {}
    public function createPackageToClientsWhereDoesntHaveAndHasNetworkIp()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        set_time_limit(0);
        ini_set('memory_limit', '8912M');


         $clientsToDeleteServices = [
            0 => 409,
            1 => 488,
            2 => 550,
            3 => 614,
            4 => 625,
            5 => 670,
            6 => 699,
            7 => 707,
            8 => 773,
            9 => 790,
            10 => 794,
            11 => 926,
            12 => 954,
            13 => 976,
            14 => 1008,
            15 => 1055,
            16 => 1056,
            17 => 1068,
            18 => 1070,
            19 => 1097,
            20 => 1109,
            21 => 1133,
            22 => 1235,
            23 => 1249,
            24 => 1269,
            25 => 1295,
            26 => 1310,
            27 => 1405,
            28 => 1443,
            29 => 1551,
            30 => 1619,
            31 => 1661,
            32 => 1677,
            33 => 1687,
            34 => 1690,
            35 => 1696,
            36 => 1717,
            37 => 1728,
            38 => 1737,
            39 => 1760,
            40 => 1765,
            41 => 1779,
            42 => 1782,
            43 => 1790,
            44 => 1806,
            45 => 1830,
            46 => 1851,
            47 => 1864,
            48 => 1881,
            49 => 1891,
            50 => 1893,
            51 => 1920,
            52 => 1946,
            53 => 1957,
            54 => 2005,
            55 => 2027,
            56 => 2037,
            57 => 2042,
            58 => 2048,
            59 => 2049,
            60 => 2055,
            61 => 2067,
            62 => 2082,
            63 => 2095,
            64 => 2097,
            65 => 2107,
            66 => 2127,
            67 => 2130,
            68 => 2133,
            69 => 2140,
            70 => 2148,
            71 => 2162,
            72 => 2166,
            73 => 2189,
            74 => 2223,
            75 => 2295,
            76 => 2338,
            77 => 2347,
            78 => 2354,
            79 => 2356,
            80 => 2379,
            81 => 2382,
            82 => 2384,
            83 => 2390,
            84 => 2392,
            85 => 2434,
            86 => 2437,
            87 => 2480,
            88 => 2481,
            89 => 2505,
            90 => 2528,
            91 => 2546,
            92 => 2547,
            93 => 2550,
            94 => 2603,
            95 => 2632,
            96 => 2642,
            97 => 2649,
            98 => 2663,
            99 => 2707,
            100 => 2721,
            101 => 2735,
            102 => 2750,
            103 => 2765,
            104 => 2767,
            105 => 2779,
            106 => 2786,
            107 => 2803,
            108 => 2809,
            109 => 2867,
            110 => 2868,
            111 => 2881,
            112 => 2955,
            113 => 2958,
            114 => 2985,
            115 => 3068,
            116 => 3103,
            117 => 3144,
            118 => 3173,
            119 => 3185,
            120 => 3225,
            121 => 3261,
            122 => 3304,
            123 => 3320,
            124 => 3338,
            125 => 3343,
            126 => 3354,
            127 => 3355,
            128 => 3409,
            129 => 3447,
            130 => 3468,
            131 => 3469,
            132 => 3473,
            133 => 3483,
            134 => 3497,
            135 => 3505,
            136 => 3515,
            137 => 3552,
            138 => 3559,
            139 => 3564,
            140 => 3566,
            141 => 3593,
            142 => 3606,
            143 => 3638,
            144 => 3640,
            145 => 3678,
            146 => 3690,
            147 => 3723,
            148 => 3741,
            149 => 3752,
            150 => 3753,
            151 => 3756,
            152 => 3762,
            153 => 3782,
            154 => 3790,
            155 => 3791,
            156 => 3793,
            157 => 3798,
            158 => 3810,
            159 => 3814,
            160 => 3823,
            161 => 3824,
            162 => 3832,
            163 => 3838,
            164 => 3855,
            165 => 3869,
            166 => 3909,
            167 => 3910,
            168 => 3916,
            169 => 3918,
            170 => 3923,
            171 => 3930,
            172 => 3934,
            173 => 3937,
            174 => 3939,
            175 => 3943,
            176 => 3946,
            177 => 3957,
            178 => 3962,
            179 => 3963,
            180 => 3972,
            181 => 3976,
            182 => 3978,
            183 => 3984,
            184 => 3985,
            185 => 3987,
            186 => 4002,
            187 => 4007,
            188 => 4009,
            189 => 4011,
            190 => 4057,
            191 => 4060,
            192 => 4075,
            193 => 4104,
            194 => 4105,
            195 => 4142,
            196 => 4179,
            197 => 4186,
            198 => 4261,
            199 => 4267,
            200 => 4270,
            201 => 4275,
            202 => 4279,
            203 => 4287,
            204 => 4302,
            205 => 4308,
            206 => 4310,
            207 => 4316,
            208 => 4325,
            209 => 4354,
            210 => 4380,
            211 => 4390,
            212 => 4397,
            213 => 4403,
            214 => 4411,
            215 => 4413,
            216 => 4443,
            217 => 4450,
            218 => 4451,
            219 => 4457,
            220 => 4462,
            221 => 4463,
            222 => 4477,
            223 => 4507,
            224 => 4564,
            225 => 4572,
            226 => 4688,
            227 => 4690,
            228 => 4701,
            229 => 4717,
            230 => 4720,
            231 => 4726,
            232 => 4739,
            233 => 4761,
            234 => 4767,
            235 => 4796,
            236 => 4829,
            237 => 4834,
            238 => 4842,
            239 => 4854,
            240 => 4860,
            241 => 4873,
            242 => 4883,
            243 => 4887,
            244 => 4895,
            245 => 4904,
            246 => 4909,
            247 => 4928,
            248 => 4943,
            249 => 4945,
            250 => 4947,
            251 => 4961,
            252 => 4965,
            253 => 4969,
            254 => 4982,
            255 => 4987,
            256 => 4988,
            257 => 4995,
            258 => 5003,
            259 => 5010,
            260 => 5026,
            261 => 5035,
            262 => 5036,
            263 => 5038,
            264 => 5040,
            265 => 5042,
            266 => 5045,
            267 => 5047,
            268 => 5056,
            269 => 5060,
            270 => 5068,
            271 => 5069,
            272 => 5070,
            273 => 5078,
            274 => 5108,
            275 => 5136,
            276 => 5149,
            277 => 5156,
            278 => 5159,
            279 => 5162,
            280 => 5166,
            281 => 5169,
            282 => 5177,
            283 => 5188,
            284 => 5202,
            285 => 5218,
            286 => 5234,
            287 => 5235,
            288 => 5249,
            289 => 5263,
            290 => 5268,
            291 => 5274,
            292 => 5297,
            293 => 5300,
            294 => 5301,
            295 => 5322,
            296 => 5332,
            297 => 5340,
            298 => 5434,
            299 => 5487,
            300 => 5510,
            301 => 5564,
            302 => 5592,
            303 => 5605,
            304 => 5618,
            305 => 5625,
            306 => 5630,
            307 => 5632,
            308 => 5644,
            309 => 5646,
            310 => 5659,
            311 => 5713,
            312 => 5722,
            313 => 5725,
            314 => 5735,
            315 => 5738,
            316 => 5741,
            317 => 5742,
            318 => 5747,
            319 => 5749,
            320 => 5828,
            321 => 5829,
            322 => 5896,
            323 => 5919,
            324 => 5938,
            325 => 5939,
            326 => 5943,
            327 => 5944,
            328 => 5947,
            329 => 5950,
            330 => 5951,
            331 => 5965,
            332 => 5966,
            333 => 5968,
            334 => 5971,
            335 => 5974,
            336 => 5975,
            337 => 5984,
            338 => 5999,
            339 => 6010,
            340 => 6012,
            341 => 6020,
            342 => 6024,
            343 => 6126,
            344 => 6315,
        ];

        foreach ($clientsToDeleteServices as $key => $clientId) {
            $this->eliminaSusPaquetesDeHoy($clientId);
        }

        $clientsInternetNotIp = Client::with(['network_ip', 'client_main_information', 'internet_service', 'payments', 'transactions'])
            ->whereHas('client_main_information')
            ->whereHas('network_ip')
            ->whereDoesntHave('internet_service')
            ->get();

        $serviciosdeInternetSInIP = [];
        foreach ($clientsInternetNotIp as $client) {
            foreach ($client->network_ip as $net) {
                $serviciosdeInternetSInIP[$client->id] = $net->id;
            }
        }

        $clientsUltimoPago = [];

        foreach ($clientsInternetNotIp as $client) {
            $payment = $client->payments->last();
            foreach ($client->payments as $payment) {
                $clientsUltimoPago[$client->id] = $payment->amount;
            }
        }

        $preciosQueNoContienenServicio = [];
        foreach ($clientsUltimoPago as $client => $price) {
            $planes = [
                300 => 29, //50mb empleaados
                309 => 30, //Internet50mb+telefoniaGratis
                311 => 30, //Internet50mb+telefoniaGratis
                275 => 30, //Internet50mb+telefoniaGratis
                349 => 30, //Internet50mb+telefoniaGratis
                350 => 30, //Internet50mb+telefoniaGratis
                370 =>  30, //Internet50mb+telefoniaGratis
                1957 =>  30, //Internet50mb+telefoniaGratis
                3490 =>  30, //Internet50mb+telefoniaGratis
                1396 =>  30, //Internet50mb+telefoniaGratis
                0 =>  30, //Internet50mb+telefoniaGratis

                530 =>  32, //Internet 150+
                420 => 31, //internet gamer 100+

                600 =>  36, //250MB
                599 =>  36, //250MB
                698 => 35, //500mb+tel+movienet

                525 =>  32, //Internet 150+
                520 =>  32, //Internet 150+
                679 =>  33, //Internet 200+

                700 => 35, //500mb+tel+movienet

                449 =>   31, //internet gamer 100+
                1040 =>  32, //Internet 150+
                690 =>  35, //500mb+tel+movienet
            ];

            $idPackage = null;
            if (isset($planes[intval($price)])) {
                $idPackage = $planes[intval($price)];
            } else {
                Log::info("no existe un Paquete con el precio: " . $price);
            }

            if ($idPackage) {
                $serviceBundle = Bundle::where('id', $idPackage)->first();

                if ($serviceBundle) {
                    $clientUser = $this->generateUser();
                    $newBundleService = [
                        'client_id' => $client,
                        'bundle_id' => $serviceBundle->id,
                        'description' => $serviceBundle->service_description,
                        'price' => $serviceBundle->price,
                        'pay_period' => 'Periodo 1',
                        'estado' => 'Activado',
                        'discount' => 0,
                        'discount_percent' => null,
                        'start_date_discount' => null,
                        'end_date_discount' => null,
                        'discount_message' => null,
                        'contract_start_date' => now(),
                        'contract_end_date' => now(),
                        'automatic_renewal' => 0,
                        'charged' => 1,
                        'deployed' => 0,
                    ];

                    $bundleId = DB::table('client_bundle_services')->insertGetId($newBundleService);
                    $bundleRepository = new BundleRepository();
                    $servicesInternets = $bundleRepository->getPlanesInternetByBundleId($serviceBundle->id);
                    if (count($servicesInternets) == 0) {
                        Log::info("EL paquete " . $serviceBundle->title . " no tiene planes de internet");
                        continue;
                    }
                    foreach ($servicesInternets as $serviceInternet) {
                        $newInternet = [
                            'client_id' => $client,
                            'internet_id' => $serviceInternet->id,
                            'client_bundle_service_id' => $bundleId,
                            'description' => $serviceInternet->service_name,
                            'amount' => 1,
                            'unity' => 1,
                            'price' => $serviceInternet->price,
                            'pay_period' => 'Periodo 1',
                            'start_date' => now(),
                            'finish_date' => now(),
                            'discount' => 0,
                            'discount_percent' => null,
                            'start_date_discount' => null,
                            'end_date_discount' => null,
                            'discount_message' => null,
                            'estado' => 'Activado',
                            'router_id' => 2,
                            'client_name' => $clientUser,
                            'user' => $clientUser,
                            'password' => $clientUser,
                            'ipv4_assignment' => "IP Estatica",
                            'ipv4' => $serviciosdeInternetSInIP[$client],
                            'additional_ipv4' => null,
                            'ipv4_pool' => null,
                            'ipv6' => null,
                            'delegated_ipv6' => null,
                            'mac' => null,
                            'portid' => null,
                            'payment_type' => null,
                            'deferred_payment_in_month' => null,
                            'cost_activation' => $serviceInternet->cost_activation,
                            'charged' => 1,
                            'deployed' => 1,
                        ];

                        $net = DB::table('client_internet_services')->insertGetId($newInternet);


                        //Actualizar el Ip con los nuevos datos
                        $this->updateIp($newInternet, $net);
                        //Actualizar ClientUser
                        $this->updateClientUser($newInternet, $net);

                        $SERVICIO = ClientInternetService::find($net);

                        $Client = Client::find($client);
                        if ($Client && $Client->client_main_information->estado !== ComunConstantsController::STATE_ACTIVE) {
                            MikrotikCreateAddressList::dispatch($SERVICIO);
                        }
                    }
                }
            } else {
                $preciosQueNoContienenServicio[$client] = $price;
            }
        }

        dd("finish");

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function eliminaSusPaquetesDeHoy($clientId)
    {
        DB::table('client_bundle_services')->where('client_id', $clientId)->delete();
        DB::table('client_internet_services')->where('client_id', $clientId)->delete();
        DB::table('client_voz_services')->where('client_id', $clientId)->delete();
    }


    public function generateUser()
    {
        $preposition = 'Meganet';
        $existingNumbers = ClientMainInformation::pluck('user')->toArray();
        do {
            $randomNumber = mt_rand(10000, 99999); // Genera un número aleatorio de 5 dígitos
            $timestamp = time(); // Obtiene la marca de tiempo actual
            $combinedValue = $preposition . $randomNumber . $timestamp;
            $hashedValue = sha1($combinedValue); // Aplica una función de hash (SHA-1) al valor combinado

            $newUser = $preposition . substr($hashedValue, 0, 8); // Utiliza solo los primeros 8 caracteres del hash como número de usuario
        } while (in_array($newUser, $existingNumbers));

        return $newUser;
    }

    public function updateIp($newInternet, $net)
    {
        $networkIp = NetworkIp::find($newInternet['ipv4']);
        if ($networkIp) {
            $networkIp->update([
                'used' => ComunConstantsController::IS_NUMERICAL_TRUE,
                'used_by' => $net,
                'client_id' => $newInternet['client_id'],
                'type_service' => 'ClientInternetService',
                'host_category' => 'Customer',
                'location_id' => 0,
                'ping' => 'Desconocido',

            ]);
        }
    }

    public function updateClientUser($newInternet, $net)
    {

        $clientUser = ClientUser::where('client_id', $newInternet['client_id'])->first();
        if ($clientUser) {
            $clientUser->update([
                'client_id' => $newInternet['client_id'],
                'router_id' => 2,
                'service_id' => $net,
                'user' => $newInternet['user'],
            ]);
        } else {
            $clientUser = new ClientUser();
            $clientUser->client_id = $newInternet['client_id'];
            $clientUser->router_id = 2;
            $clientUser->service_id = $net;
            $clientUser->user = $newInternet['user'];
            $clientUser->save();
        }
    }
}
