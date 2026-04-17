<?php


namespace App\Http\Traits\Models\NetworkIp;

 const icon = [
     'Customer' => 'fa fa-user',
     'Ninguno' => 'far fa-circle',
     'Access point' => 'fas fa-wifi',
     'Firewall' => 'fas fa-shield-alt',
     'DB Server' => 'fas fa-database',
     'L2 Device' => 'fas fa-arrows-alt',
     'L3 Device' => 'fas fa-arrows-alt',
     'Router' => 'fas fa-network-wired',
     'Linux Server' => 'fas fa-server',
     'Windows Server' => 'fas fa-server',
     'Printer' => 'fas fa-print',
     'VoIP Device' => 'fas fa-phone-alt',
     'Workstation' => 'fas fa-desktop',
     'Other' => 'fas fa-tag',
     'Todo' => 'far fa-circle'
 ];

trait NetworkIpTrait
{
    public function networkIpGetIconForHostCategory($hostCategory)
    {
        return icon[$hostCategory];
    }
}
