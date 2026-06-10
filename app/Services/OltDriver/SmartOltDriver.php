<?php

namespace App\Services\OltDriver;

use App\Services\OLTsService;
use App\Services\OltDriver\Capabilities\SupportsAdvancedDiagnostics;
use App\Services\OltDriver\Capabilities\SupportsAdvancedOnuConfig;
use App\Services\OltDriver\Capabilities\SupportsBulkOperations;
use App\Services\OltDriver\Capabilities\SupportsCatalog;
use App\Services\OltDriver\Capabilities\SupportsCatv;
use App\Services\OltDriver\Capabilities\SupportsOltTopology;
use App\Services\OltDriver\Capabilities\SupportsOnuPorts;
use App\Services\OltDriver\Capabilities\SupportsVlanManagement;
use App\Services\OltDriver\Capabilities\SupportsVoip;

/**
 * Driver SmartOLT — implementa OltDriverInterface y todas las capacidades
 * delegando en OLTsService por composición. No duplica lógica HTTP, budget
 * ni jitter — esa infraestructura vive en OLTsService y se hereda gratis.
 *
 * Para el Bloque B: un HuaweiDriver implementaría las mismas interfaces
 * llamando a un cliente SNMP/Netconf en lugar de Guzzle/SmartOLT REST.
 */
class SmartOltDriver implements
    OltDriverInterface,
    SupportsCatalog,
    SupportsOltTopology,
    SupportsAdvancedDiagnostics,
    SupportsOnuPorts,
    SupportsVoip,
    SupportsCatv,
    SupportsVlanManagement,
    SupportsAdvancedOnuConfig,
    SupportsBulkOperations
{
    public function __construct(private OLTsService $service) {}

    // ── OltDriverInterface — núcleo ───────────────────────────────────────

    public function getName(): string
    {
        return 'SmartOLT';
    }

    public function listOlts(): array
    {
        return $this->service->getOlts();
    }

    public function listSpeedProfiles(): array
    {
        return $this->service->getSpeedProfiles();
    }

    public function getUnconfiguredOnus(?string $oltId = null): array
    {
        return $this->service->getUnconfiguredONUs($oltId);
    }

    public function getOnusByOlt(string $oltId): array
    {
        return $this->service->getONUsByOlt($oltId);
    }

    public function getOnusSignals(?string $oltId = null): array
    {
        return $this->service->getONUsSignals($oltId);
    }

    public function getOnusStatus(?string $oltId = null): array
    {
        return $this->service->getONUsStatus($oltId);
    }

    public function getOnuDetails(string $onuId): array
    {
        return $this->service->getOnuDetailsByExternalId($onuId);
    }

    public function getOnuSignal(string $onuId): array
    {
        return $this->service->getSignalByExternalId($onuId);
    }

    public function getOnuStatus(string $onuId): array
    {
        return $this->service->getStatusByExternalId($onuId);
    }

    public function authorizeOnu(array $data): array
    {
        return $this->service->registerOnu($data);
    }

    public function deauthorizeOnu(string $onuId): array
    {
        return $this->service->removeONU($onuId);
    }

    public function setOnuEnabled(string $onuId, bool $enabled): array
    {
        return $this->service->enableDisableONU($onuId, $enabled);
    }

    public function rebootOnu(string $onuId): array
    {
        return $this->service->rebootONU($onuId);
    }

    public function setOnuSpeedProfile(string $onuId, array $data): array
    {
        return $this->service->updateSpeedProfile($onuId, $data);
    }

    // ── SupportsCatalog ───────────────────────────────────────────────────

    public function listZones(): array
    {
        return $this->service->getZones();
    }

    public function listOnuTypes(): array
    {
        return $this->service->getTypeONUs();
    }

    public function listOdbs(): array
    {
        return $this->service->getODBs();
    }

    // ── SupportsOltTopology ───────────────────────────────────────────────

    public function getOltCards(string $oltId): array
    {
        return $this->service->getCardsByOlt($oltId);
    }

    public function getOltPonPorts(string $oltId): array
    {
        return $this->service->getPonPortsByOlt($oltId);
    }

    public function getOltUplinkPorts(string $oltId): array
    {
        return $this->service->getUplinkPortsByOlt($oltId);
    }

    public function getOltOutagePons(string $oltId): array
    {
        return $this->service->getOutagePONsByOlt($oltId);
    }

    public function getOltVlans(string $oltId): array
    {
        return $this->service->getVLANsByOlt($oltId);
    }

    public function getOltEnvironmentStats(): array
    {
        return $this->service->getUpTimeEnviromentTemperatureByOlt();
    }

    // ── SupportsAdvancedDiagnostics ───────────────────────────────────────

    public function getOnuFullStatus(string $onuId): array
    {
        return $this->service->getFullStatus($onuId);
    }

    public function getOnuRunningConfig(string $onuId): array
    {
        return $this->service->getRunningConfig($onuId);
    }

    public function getOnuMgmtIp(string $onuId): array
    {
        return $this->service->getMgmTIp($onuId);
    }

    public function getOnuIpAddress(string $onuId): array
    {
        return $this->service->getIpAddress($onuId);
    }

    public function getOnuDetailsBySn(string $sn): array
    {
        return $this->service->getOnuDetailsBySN($sn);
    }

    // ── SupportsOnuPorts ──────────────────────────────────────────────────

    public function configureOnuEthernetPort(string $onuId, array $data, string $type = 'lan'): array
    {
        return $this->service->configureEhernetPort($onuId, $data, $type);
    }

    public function shutdownOnuEthernetPort(string $onuId, array $data): array
    {
        return $this->service->shutdownEhernetPort($onuId, $data);
    }

    public function configureOnuWifiPort(string $onuId, array $data, string $type = 'lan'): array
    {
        return $this->service->configureWifiPort($onuId, $data, $type);
    }

    public function shutdownOnuWifiPort(string $onuId, array $data): array
    {
        return $this->service->shutdownWifiPort($onuId, $data);
    }

    // ── SupportsVoip ──────────────────────────────────────────────────────

    public function setOnuVoipPort(string $onuId, string $status, array $data): array
    {
        return $this->service->setOnuVoipPort($onuId, $status, $data);
    }

    public function updateOnuMgmtAndVoip(string $onuId, array $data): array
    {
        return $this->service->updateMgmtAndVoIp($onuId, $data);
    }

    // ── SupportsCatv ──────────────────────────────────────────────────────

    public function setOnuCatv(string $onuId, string $catv = 'enable'): array
    {
        return $this->service->setCATV($onuId, $catv);
    }

    // ── SupportsVlanManagement ────────────────────────────────────────────

    public function setOnuVlans(string $onuId, array $data): array
    {
        return $this->service->changeAttachedVlans($onuId, $data);
    }

    public function addVlan(string $oltId, array $data): array
    {
        return $this->service->addVlan($oltId, $data);
    }

    // ── SupportsAdvancedOnuConfig ─────────────────────────────────────────

    public function setOnuWanMode(string $onuId, string $mode, array $data): array
    {
        // OLTsService::setWanMode tiene el orden de parámetros invertido por un bug
        // (opcional antes de requerido). Se llama explícitamente con el orden correcto.
        return $this->service->setWanMode($onuId, $mode, $data);
    }

    public function updateOnuMode(string $onuId, array $data): array
    {
        return $this->service->updateMode($onuId, $data);
    }

    public function changeOnuType(string $onuId, array $data): array
    {
        return $this->service->changeOnuType($onuId, $data);
    }

    public function updateOnuPonChannel(string $onuId, array $data): array
    {
        return $this->service->updateChannel($onuId, $data);
    }

    public function changeOnuWebPassword(string $onuId): array
    {
        return $this->service->changeWebUserPass($onuId);
    }

    public function resyncOnuConfig(string $onuId): array
    {
        return $this->service->resyncONUConfig($onuId);
    }

    public function moveOnu(string $onuId, array $data): array
    {
        return $this->service->moveONU($onuId, $data);
    }

    public function updateOnuServicePort(string $onuId, array $data): array
    {
        return $this->service->updateServicePort($onuId, $data);
    }

    public function setOnuMgmtIp(string $onuId, array $data, string $type = 'inactive'): array
    {
        return $this->service->setOnuMgmtIp($onuId, $data, $type);
    }

    // ── SupportsBulkOperations ────────────────────────────────────────────

    public function bulkSetSpeedProfile(array $data): array
    {
        return $this->service->updateBulkSpeedProfile($data);
    }

    public function getOnusBulk(string $oltId): array
    {
        return $this->service->getAllOnuDataParallel($oltId);
    }
}
