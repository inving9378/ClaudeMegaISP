<?php

namespace App\Services\OltDriver;

/**
 * Stub del driver ZTE — reserva el espacio en OltDriverManager sin implementar
 * ningún protocolo real. Item roadmap #283 (decisión de Irving: priorizar ZTE
 * primero, condicionado a hardware piloto que aún no está confirmado).
 *
 * Recomendación original: docs/MULTIOLT_SAAS_DISENO.md §5 — "no implementar
 * ahora" un ZteDriver real sin OLT ZTE accesible para validar (protocolo CLI/
 * REST varía por modelo/firmware; código sin hardware = bugs silenciosos en
 * producción de un ISP cliente). Toda operación lanza RuntimeException hasta
 * que exista un ZteDriver real respaldado por hardware confirmado.
 */
class NullZteDriver implements OltDriverInterface
{
    private const NOT_IMPLEMENTED = 'ZTE driver no implementado';

    public function getName(): string
    {
        return 'ZTE';
    }

    public function listOlts(): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function listSpeedProfiles(): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getUnconfiguredOnus(?string $oltId = null): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getOnusByOlt(string $oltId): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getOnusSignals(?string $oltId = null): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getOnusStatus(?string $oltId = null): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function findOnuBySn(string $sn): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getOnuDetails(string $onuId): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getOnuSignal(string $onuId): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function getOnuStatus(string $onuId): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function authorizeOnu(array $data): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function deauthorizeOnu(string $onuId): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function setOnuEnabled(string $onuId, bool $enabled): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function rebootOnu(string $onuId): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }

    public function setOnuSpeedProfile(string $onuId, array $data): array
    {
        throw new \RuntimeException(self::NOT_IMPLEMENTED);
    }
}
