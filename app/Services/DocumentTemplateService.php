<?php

namespace App\Services;

use App\Http\Repository\CompanyInformationRepository;
use App\Models\CompanyInformation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateService
{

    const DATA_CLIENT_VARIABLES_VALUE  = [
        'Client' => [
            'Cliente' => [
                'data.id' => "Id",
                'data.user' => 'Usuario',
                'data.password' => 'Contraseña',
                'data.estado' => 'Estado del cliente',
                'data.full_name' => 'Nombre Completo',
                'data.email' => 'Email',
                'data.phone' => 'Teléfono',
                'data.phone2' => 'Teléfono 2',
                'data.phone3' => 'Teléfono 3',
                'data.category' => 'Categoría',
                'data.partner' => 'Socio',
                'data.street' => 'Calle',
                'data.zip' => 'Código Zip',
                'data.created_at' => 'Fecha Añadida',
                'data.geo_data' => 'Datos Geográficos',
                'data.created_by' => 'Añadido por ID',
                'data.name' => 'Nombre',
                'data.father_last_name' => 'Apellido paterno',
                'data.mother_last_name' => 'Apellido materno',
                'data.external_number' => 'Número exterior',
                'data.internal_number' => 'Número interior',
                'data.state' => 'Estado',
                'data.municipality' => 'Municipio',
                'data.colony' => 'Colonia',
                'data.ift' => 'Ift',
                'data.power_dbm' => 'Potencia en dBm',
                'data.original_password' => 'Contraseña Original',
                'data.user_film' => 'Usuario Películas',
                'data.password_film' => 'Contraseña Películas',
                'data.password_wifi' => 'Contraseña Wifi',
                'data.reinstatement' => 'Reinstalación',
                'data.installation_on_time' => 'El Técnico Instaló en Tiempo y Forma',
                'data.technician_attencion' => 'El técnico le atendio con amabilidad y respeto si, no y porque',
                'data.amount_technician_and_why' => 'Cual fue el monto que cobro el técnico y porque',
                'data.comment' => 'Comment',
                'data.doubt_signed_contract' => 'Tiene dudas acerca del contrato que esta firmando',
                'data.box_nomenclator' => 'Nomenclatura de Caja',
                'data.social_id' => 'Social Id',
                'data.modem_sn' => 'S/N (SERIE MODEM)',
                'data.gpon_ont' => 'GPON ONT',
                'data.conection_type' => 'Tipo de Conexión',
                'data.nif_pasaport' => 'NIF/Pasaporte',
            ],
            'Servicios' => [
                'data.table_client_services' => 'Tabla de Servicios de Clientes',
                'data.client_bundle_service_name' => 'Nombre del Paquete',
                'data.client_internet_service_name' => 'Nombre del Servicio Internet',
                'data.client_internet_service_ip' => 'IP del Servicio Internet',
                'data.client_voz_service_name' => 'Nombre del Servicio Voz',
                'data.client_voz_service_phone' => 'Telefono del Servicio Voz',
                'data.client_custom_service_name' => 'Nombre del Servicio Custom',

            ],
            'Facturación del Cliente' => [
                'data.type_of_billing_id' => 'Tipo de facturación',
                'data.fecha_corte' => 'Fecha de Corte',
                'data.fecha_pago' => 'Fecha de Pago',
                'data.amount' => 'Saldo de la Cuenta',
                'data.table_client_pending_payments' => 'Tabla de Pagos Pendientes',
                'data.cost_all_services' => 'Costo de Todos los Servicios'
            ],
            'Otras' => [
                'data.image_src' => 'Imagen Base',
                'data.now' => 'Fecha de Hoy'
            ],
            'Hidden' => [],
        ],
        'Crm' => [
            'Crm' => [
                'data.id' => "Id",
                'data.password' => 'Contraseña',
                'data.email' => 'Email',
                'data.phone' => 'Teléfono',
                'data.ift' => 'Ift',
                'data.partner' => 'Socio',
                'data.geo_data' => 'Datos Geográficos',
                'data.created_by' => 'Añadido por ID',
                'data.name' => 'Nombre',
                'data.father_last_name' => 'Apellido paterno',
                'data.mother_last_name' => 'Apellido materno',
                'data.external_number' => 'Número exterior',
                'data.internal_number' => 'Número interior',
                'data.phone2' => 'Teléfono 2',
                'data.phone3' => 'Teléfono 3',

                'data.zip' => 'Código Zip',
                'data.created_at' => 'Fecha Añadida',
                'data.street' => 'Calle',
                'data.category' => 'Categoría',
                'data.state' => 'Estado',
                'data.municipality' => 'Municipio',
                'data.colony' => 'Colonia',
                'data.nif_pasaport' => 'NIF/Pasaporte',
            ],
            'Otras' => [
                'data.image_src' => 'Imagen Base',
                'data.now' => 'Fecha de Hoy'
            ],
            'Hidden' => [],
        ],
        'Comun' => [
            'Informacion de la empresa' => [
                'data.company_name' => 'Nombre de la Empresa',
                'data.company_postal_code' => 'Código Postal',
                'data.country' => 'País',
                'data.colony_id' => 'Colonia',
                'data.state_id' => 'Estado',
                'data.municipality_id' => 'Municipio',
                'data.email' => 'Email',
                'data.atention_client_phone' => 'Teléfono de Atencion al Cliente',
                'data.rfc' => 'RFC',
                'data.iva' => 'IVA',
                'data.bank_name' => 'Banco',
                'data.bank_account' => 'Cuenta Bancaria',
                'data.url_portal' => 'Enlace a Portal',
                'data.company_street' => 'Calle',
                'data.company_external_number' => 'Número Exterior',
                'data.company_internal_number' => 'Número Interior',
                'data.url_logo' => 'Logo',
            ],
            'Variables Dinamicas' => [
                ///Variables dinamicas Estableceremos metodos para ellas
                'data_dinamic.payment_id' => 'Id del Pago',
                'data_dinamic.payment_date' => 'Fecha del Pago', //Relacionada con data_dinamic.payment_id
                'data_dinamic.payment_amount' => 'Amount del Pago', //Relacionada con data_dinamic.payment_id
                'data_dinamic.payment_period' => 'Periodo del Pago', //Relacionada con data_dinamic.payment_id
                'data_dinamic.payment_receipt' => 'Recibo del Pago', //Relacionada con data_dinamic.payment_id
                'data_dinamic.debit' => 'Debito',
                'data_dinamic.invoice_id' => 'Factura',
                'data_dinamic.invoice_date' => 'Fecha de la Factura',
                'data_dinamic.invoice_pay_up' => 'Proximo Pago',
                'data_dinamic.invoice_period' => 'Periodo',
            ]
        ]
    ];

    public function getVariables($module)
    {
        if ($module == "DocumentTemplate") {
            $variables = [];
            $array = array_merge(self::DATA_CLIENT_VARIABLES_VALUE['Client'], self::DATA_CLIENT_VARIABLES_VALUE['Crm'], self::DATA_CLIENT_VARIABLES_VALUE['Comun']);
            $variables = $array;
        } else {
            $variables = array_merge(self::DATA_CLIENT_VARIABLES_VALUE[$module], self::DATA_CLIENT_VARIABLES_VALUE['Comun']);
        }
        return [
            'variables' => $variables,
        ];
    }

    public function validateAndReplaceTemplate($html, $data = null, $module = null)
    {
        if (isset($html) && $html == '' || $html == null) {
            return array(
                'status' => 'fail',
                'keys' => ['No existe nada en el html']
            );
        }

        if (strpos($html, 'style="display: block"') !== false) {
            return array(
                'status' => 'fail',
                'keys' => ['No es posible procesar lo siguiente: style="display: block"']
            );
        }

        if (isset(self::DATA_CLIENT_VARIABLES_VALUE[$module])) {
            $array = [];
            $dataArray = array_merge(
                self::DATA_CLIENT_VARIABLES_VALUE[$module],
            );
            foreach ($dataArray as $key => $value) {
                foreach ($value as $key2 => $value2) {
                    $array[$key2] = $value2;
                }
            }

            $dataArray = $array;
        } else {
            $dataArray = array_merge(
                self::DATA_CLIENT_VARIABLES_VALUE['Client']['Cliente'],
                self::DATA_CLIENT_VARIABLES_VALUE['Client']['Hidden'],
                self::DATA_CLIENT_VARIABLES_VALUE['Client']['Facturación del Cliente'],
                self::DATA_CLIENT_VARIABLES_VALUE['Client']['Otras'],
                self::DATA_CLIENT_VARIABLES_VALUE['Client']['Servicios'],
            );
        }


        $dataArrayKeys = array_keys($dataArray);

        preg_match_all('/\${(.*?)}/', $html, $matches);
        $keyFound = $matches[1];
        $keys = [];
        $comunKeys = array_keys(self::DATA_CLIENT_VARIABLES_VALUE['Comun']);
        $keysNotValidateNecesary = [];
        foreach ($comunKeys as $key) {
            $keysNotValidateNecesary[] = array_keys(self::DATA_CLIENT_VARIABLES_VALUE['Comun'][$key]);
        }
        // Asegúrate de que $keysNotValidateNecesary sea un array plano
        $flatKeysNotValidate = array_merge(...$keysNotValidateNecesary);

        foreach ($keyFound as $key) {
            if (in_array($key, $dataArrayKeys) && $data) {
                $keyData = str_replace('data.', '', $key);

                if ($keyData == 'image_src') {
                    $html = str_replace('${' . $key . '}', public_path(), $html);
                } elseif ($keyData == 'table_client_services') {
                    $html = str_replace('${' . $key . '}', $this->getHtmlTableClientWithServices($data[$keyData]), $html);
                } elseif ($keyData == 'table_client_pending_payments') {
                    $html = str_replace('${' . $key . '}', $this->getHtmlTableClientPendingPayments($data[$keyData]), $html);
                } else {
                    $html = str_replace('${' . $key . '}', $data[$keyData], $html);
                }
            } elseif (in_array($key, $flatKeysNotValidate)) {
                if(strpos($key, 'data_dinamic.') !== false) {

                }
            }
            else {
                $dataComun = $this->getDataComun();
                // Verificamos si la clave está en los datos comunes
                $keyDataComun = str_replace('data.', '', $key);
                if (array_key_exists($keyDataComun, $dataComun) && $keyDataComun == 'url_logo') {
                    $url = asset(str_replace('public', 'storage', str_replace("\\", "/", $dataComun[$keyDataComun])));
                    $html = str_replace('${' . $key . '}', $url, $html);
                } elseif (array_key_exists($keyDataComun, $dataComun)) {
                    $html = str_replace('${' . $key . '}', $dataComun[$keyDataComun], $html);
                }
                // Si no está en datos comunes ni en dataArrayKeys, lo añadimos a keys
                if ($data && !in_array($key, $flatKeysNotValidate)) {
                    $keys[] = $key;
                }
            }
        }

        if (count($keys)) {
            return [
                'status' => 'fail',
                'keys' => $keys,
                'html' => $html
            ];
        }

        return [
            'status' => 'ok',
            'html' => $html
        ];
    }

    public function saveDocumentTemplate($filePath, $html)
    {
        ini_set('memory_limit', '-1');
        $directoryPath = dirname(storage_path('app/public/' . $filePath));
        if (!file_exists($directoryPath)) {
            if (!mkdir($directoryPath, 0775, true) && !is_dir($directoryPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directoryPath));
            }
            // Asigna los permisos al directorio recién creado
            chmod($directoryPath, 0775);
        }

        $html = str_replace('\n', '', $html);
        // Convertir la vista a PDF
        $pdf = Pdf::loadHTML($html);
        $output = $pdf->output();
        Storage::disk('public')->put($filePath, $output);
        $tempFilePath = 'document_template/document/temp_template/new/new.pdf';
        $this->deleteTemplateFile($tempFilePath);
    }


    public function saveTemporalTemplateAndReturnPath($validation, $tempFilePath)
    {
        ini_set('memory_limit', '-1');
        $directoryPath = dirname(storage_path('app/public/' . $tempFilePath));
        if (!file_exists($directoryPath)) {
            if (!mkdir($directoryPath, 0775, true) && !is_dir($directoryPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directoryPath));
            }
            // Asigna los permisos al directorio recién creado
            chmod($directoryPath, 0775);
        }

        // Convertir la vista a PDF
        $html = str_replace('\n', '', $validation);

        $pdf = Pdf::loadHTML($html);
        $output = $pdf->output();
        Storage::disk('public')->put($tempFilePath, $output);
        return response()->json([
            'status' => 'ok',
            'file_path' => '/storage/' . $tempFilePath
        ]);
    }

    public function returnPath($validation)
    {
        ini_set('memory_limit', '-1');

        // Convertir la vista a PDF
        $html = str_replace('\n', '', $validation);

        $pdf = Pdf::loadHTML($html);
        $output = $pdf->output();

        // Devuelve el PDF como respuesta con encabezado para mostrar en otra pestaña
        return response($output, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="preview.pdf"');
    }

    public function deleteTemplateFile($tempFilePath)
    {
        if (Storage::disk('public')->exists($tempFilePath)) {
            Storage::disk('public')->delete($tempFilePath);
        }
    }


    public function getDataComun()
    {
        $companYInformationRepository = new CompanyInformationRepository();
        return $companYInformationRepository->getDataCompany();
    }


    public function getHtmlTableClientWithServices($data)
    {
        $html = view('meganet.module.client.template.html_table_with_services', [
            'client_services' => $data
        ])->toHtml();
        return $html;
    }

    public function getHtmlTableClientPendingPayments($data)
    {
        $html = view('meganet.module.client.template.html_table_pending_payments', [
            'data' => $data
        ])->toHtml();
        return $html;
    }
}
