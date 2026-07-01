<?php

namespace App\Modules\Addons\Payments\Services;

use App\Modules\Addons\Payments\Models\ClientPaymentReference;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Genera y valida la clave de conciliación por cliente.
 *
 * Formato: MEG-{client_id 8 díg}-{CC}
 *   CC = 2 dígitos de control mod-97 (ISO 7064 MOD 97-10, estilo IBAN), que
 *   detectan typos y transposiciones al teclear la referencia en el banco.
 *
 * Verificación: (payload*100 + CC) mod 97 == 1, con payload = client_id.
 */
class PaymentReferenceService
{
    public const ALGO_VERSION = 1;

    /** Dos dígitos de control mod-97 para el id de cliente dado. */
    public static function checkDigits(int $clientId): string
    {
        // payload*100 mod 97; CC = 98 - r ∈ [2,98] → siempre 2 dígitos.
        $r = ($clientId * 100) % 97;
        return sprintf('%02d', 98 - $r);
    }

    /** Construye la referencia canónica (no persiste). */
    public static function build(int $clientId): string
    {
        return sprintf('MEG-%08d-%s', $clientId, self::checkDigits($clientId));
    }

    /** True si la referencia tiene formato y verificador válidos. */
    public static function isValid(string $reference): bool
    {
        if (!preg_match('/^MEG-(\d{8})-(\d{2})$/', strtoupper(trim($reference)), $m)) {
            return false;
        }
        $clientId = (int) $m[1];
        return (($clientId * 100) + (int) $m[2]) % 97 === 1;
    }

    /** Extrae el client_id de una referencia válida, o null. */
    public static function clientIdFrom(string $reference): ?int
    {
        if (!self::isValid($reference)) {
            return null;
        }
        preg_match('/^MEG-(\d{8})-\d{2}$/', strtoupper(trim($reference)), $m);
        return (int) $m[1];
    }

    /**
     * Garantiza que el cliente tenga su referencia inmutable. Es la RED DE
     * SEGURIDAD de la generación perezosa: refuerza al ClientObserver para los
     * clientes que nacieron por caminos que NO disparan eventos Eloquent
     * (import con insertGetId/insert bulk, seeders, factory). Ver auditoría de
     * caminos de alta.
     *
     * Idempotente: si ya existe la devuelve sin regenerarla (la referencia NO
     * cambia). Seguro ante concurrencia: si dos procesos intentan crearla a la
     * vez, el UNIQUE(client_id) hace fallar al segundo y aquí se re-lee.
     *
     * @param int|\Illuminate\Database\Eloquent\Model $client id del cliente o el modelo Client.
     */
    public static function ensureFor($client): ClientPaymentReference
    {
        $clientId = is_object($client) ? (int) $client->id : (int) $client;

        // Camino rápido: ya existe.
        $existing = ClientPaymentReference::where('client_id', $clientId)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return ClientPaymentReference::create([
                'client_id'    => $clientId,
                'reference'    => self::build($clientId),
                'algo_version' => self::ALGO_VERSION,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Carrera: otro proceso la creó entre el SELECT y el INSERT → re-leer.
            $ref = ClientPaymentReference::where('client_id', $clientId)->first();
            if ($ref) {
                return $ref;
            }
            throw $e;
        }
    }
}
