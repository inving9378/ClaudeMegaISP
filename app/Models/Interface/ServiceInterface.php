<?php

namespace App\Models\Interface;

interface ServiceInterface
{
    public function getClientWithBalanceAndBillingConfiguration();
    public function haveTransaction(): bool;
    public function getPlanRelation();
    public function getService();
    public function getRepository();
    public function getInstalationCostAttribute();
    public function getHasActiveInstalationCostAttribute();
    public function getPriceServiceAttribute();
    public function getServiceNameAttribute();
    public function getTax();
}
