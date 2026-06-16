<?php

namespace App\Modules\Addons\PortalCliente\Services;

use Openpay\Data\Openpay;
use Openpay\Data\OpenpayApi;
use Openpay\Data\OpenpayApiError;
use Openpay\Data\OpenpayApiRequestError;
use Openpay\Data\OpenpayApiTransactionError;
use RuntimeException;

/**
 * Servicio de cobro con OpenPay.
 *
 * La PRIVATE_KEY solo se usa aquí (backend). Nunca aparece en frontend,
 * Blade, JS ni en ningún archivo versionado.
 *
 * En sandbox, OpenPay acepta números de tarjeta de prueba sin cobrar dinero real.
 * Para producción: cambiar OPENPAY_SANDBOX=false en .env (solo configuración, no código).
 */
class OpenpayService
{
    private OpenpayApi $api;

    public function __construct()
    {
        if (! config('openpay.sandbox')) {
            throw new RuntimeException(
                'OPENPAY_SANDBOX debe ser true. Cambia el .env antes de habilitar producción.'
            );
        }

        Openpay::setId(config('openpay.id'));
        Openpay::setApiKey(config('openpay.private_key'));
        Openpay::setCountry('MX');
        Openpay::setEndpointUrl('MX');   // rellena $apiSandboxEndpoint; sin esto el SDK lanza "No API endpoint set"
        Openpay::setSandboxMode(true);

        $this->api = OpenpayApi::getInstance(null);
    }

    /**
     * Crea un cargo síncrono con tarjeta tokenizada.
     *
     * @param string $token           Token generado por openpay.js en el navegador
     * @param string $deviceSessionId ID antifraude generado por openpay.js
     * @param float  $amount          Monto exacto de la factura (NO viene del request)
     * @param string $orderId         ID de idempotencia (único por intento)
     * @param string $description     Descripción del cargo
     * @param array  $customer        ['name', 'last_name', 'email', 'phone_number']
     * @param string $clientIp        IP real del dispositivo del cliente (para X-Forwarded-For)
     * @return object Cargo de OpenPay con ->id, ->status, ->amount, ->authorization
     */
    public function cobrarTarjeta(
        string $token,
        string $deviceSessionId,
        float  $amount,
        string $orderId,
        string $description,
        array  $customer,
        string $clientIp
    ): object {
        // IP del cliente → X-Forwarded-For enviado a OpenPay (requerido para antifraude)
        Openpay::setPublicIp($clientIp);

        $chargeData = [
            'method'            => 'card',
            'source_id'         => $token,
            'amount'            => round($amount, 2),
            'currency'          => 'MXN',
            'description'       => $description,
            'order_id'          => $orderId,
            'device_session_id' => $deviceSessionId,
            'customer'          => [
                'name'         => $customer['name'],
                'last_name'    => $customer['last_name'] ?? '',
                'email'        => $customer['email'] ?? '',
                'phone_number' => $customer['phone_number'] ?? '',
            ],
        ];

        try {
            $charge = $this->api->charges->add($chargeData);
            return $charge;
        } catch (OpenpayApiTransactionError $e) {
            // Tarjeta rechazada, fondos insuficientes, fraude, etc.
            throw new OpenpayTransactionException(
                $this->humanizeError($e->getErrorCode()),
                $e->getErrorCode(),
                $e
            );
        } catch (OpenpayApiRequestError $e) {
            throw new OpenpayTransactionException(
                'Error en la solicitud al procesador de pagos.',
                $e->getErrorCode(),
                $e
            );
        } catch (OpenpayApiError $e) {
            throw new OpenpayTransactionException(
                'Error al conectar con el procesador de pagos.',
                0,
                $e
            );
        }
    }

    /**
     * Consulta un cargo existente por su transaction_id de OpenPay.
     */
    public function consultarCargo(string $transactionId): object
    {
        try {
            return $this->api->charges->get($transactionId);
        } catch (OpenpayApiError $e) {
            throw new OpenpayTransactionException(
                'No se pudo consultar el cargo: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Convierte error codes de OpenPay a mensajes amigables para el cliente.
     * Códigos: https://www.openpay.mx/docs/error-codes.html
     */
    private function humanizeError(int $code): string
    {
        return match ($code) {
            3001 => 'Tu tarjeta fue declinada. Contacta a tu banco o intenta con otra tarjeta.',
            3002 => 'Tu tarjeta está vencida. Por favor usa una tarjeta vigente.',
            3003 => 'Tu tarjeta no tiene fondos suficientes para realizar este pago.',
            3004 => 'Tu tarjeta fue reportada como robada. Contacta a tu banco.',
            3005 => 'El pago fue rechazado por sospecha de fraude. Contacta a tu banco.',
            3006 => 'La operación no está permitida por tu banco. Contáctalos directamente.',
            3009 => 'Tu tarjeta fue reportada como perdida. Contacta a tu banco.',
            3010 => 'Tu banco rechazó el pago. Intenta con otra tarjeta o método de pago.',
            3011 => 'Tu banco rechazó el pago. Por favor contacta a tu banco.',
            default => 'El pago fue rechazado. Intenta de nuevo o usa otra tarjeta.',
        };
    }
}

/**
 * Excepción tipada para fallos de transacción OpenPay.
 * Permite al controlador capturarla sin atrapar excepciones genéricas.
 */
class OpenpayTransactionException extends \RuntimeException
{
    private int $openpayCode;

    public function __construct(string $message, int $openpayCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $openpayCode, $previous);
        $this->openpayCode = $openpayCode;
    }

    public function getOpenpayCode(): int
    {
        return $this->openpayCode;
    }
}
