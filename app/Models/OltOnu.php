<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltOnu extends Model
{
    use HasFactory;

    protected $fillable = [
        'sn',
        'unique_external_id',
        'board',
        'port',
        'administrative_status',
        'address',
        'mode',
        'name',
        'onu',
        'onu_type_id',
        'onu_type_name',
        'pon_type',
        'signal',
        'status',
        'zone_name',
        'olt_id',
        'last_synced_at',
        'tr069',
        'tr069_profile',
        'catv',
        'zone_id',
        'wan_mode',
        'odb_name',
        'longitude',
        'latitude',
        'olt_name',
        'contact',
        'signal_1310',
        'signal_1490',
        'authorization_date',
        'service_ports',
        'ethernet_ports',
        'wifi_ports',
        'voip_ports',
        'voip_service',
        'vlan',
        'mgmt_ip_address',
        'mgmt_ip_mode',
        'mgmt_ip_service_port',
        'mgmt_ip_vlan',
        'mgmt_ip_subnet_mask',
        'mgmt_ip_default_gateway',
        'mgmt_ip_dns1',
        'mgmt_ip_dns2',
        'mgmt_ip_cvlan',
        'mgmt_ip_svlan',
        'mgmt_ip_tag_transform_mode',
        'custom_template_name',
        'ip_address',
        'subnet_mask',
        'default_gateway',
        'dns1',
        'dns2',
        'username',
        'password',
        'last_status_change'
    ];

    protected $appends = ['status_cls', 'signal_cls', 'loading', 'last_synced_at_humans', 'authorization_date_humans', 'last_status_change_humans', 'icon', 'onu_nomenclature', 'onu_mode', 'onu_mgmt_ip', 'onu_signal', 'onu_pon_type', 'configured', 'capabilities'];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'authorization_date' => 'datetime',
        'last_status_change' => 'datetime',
        'service_ports' => 'json',
        'ethernet_ports' => 'json',
        'wifi_ports' => 'json',
        'voip_ports' => 'json',
    ];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function typeOnu()
    {
        return $this->belongsTo(OltTypeONU::class, 'onu_type_id');
    }

    public function getStatusClsAttribute()
    {
        $status = $this->status;
        $cls = null;
        switch ($status) {
            case 'Online':
                $cls = 'positive';
                break;
            case 'Offline':
                $cls = 'grey';
                break;
            case 'Power fail':
                $cls = 'grey';
                break;
            case 'LOS':
                $cls = 'negative';
                break;
            default:
                $cls = 'brown';
                break;
        }
        return $cls;
    }

    public function getSignalClsAttribute()
    {
        $signal = $this->signal;
        $cls = null;
        switch ($signal) {
            case 'Very good':
                $cls = 'positive';
                break;
            case 'Critical':
                $cls = 'negative';
                break;
            case 'Warning':
                $cls = 'warning';
                break;
            default:
                $cls = 'brown';
                break;
        }
        return $cls;
    }

    public function getIconAttribute()
    {
        $status = $this->status;
        $cls = null;
        switch ($status) {
            case 'Online':
                $cls = 'mdi-earth';
                break;
            case 'Offline':
                $cls = 'mdi-earth';
                break;
            case 'Power fail':
                $cls = 'fa fa-plug';
                break;
            case 'LOS':
                $cls = 'mdi-link-variant-off';
                break;
            default:
                break;
        }
        return $cls;
    }

    public function getLoadingAttribute()
    {
        return false;
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at?->diffForHumans() ?? null;
    }

    public function getLastStatusChangeHumansAttribute()
    {
        return $this->last_status_change ? $this->last_status_change->diffForHumans() : null;
    }

    public function getAuthorizationDateHumansAttribute()
    {
        return $this->authorization_date ? $this->authorization_date->format('d/m/Y h:i:s A') : 'Desconcida';
    }

    public function getOnuNomenclatureAttribute()
    {
        return sprintf('%s-onu_%d/%d/%d:%d', $this->pon_type, $this->onu, $this->board, $this->port, $this->onu);
    }

    public function getOnuModeAttribute()
    {
        return sprintf('%s - %s vlan: %s', $this->mode, $this->mode === 'Bridging' ? 'Main' : 'WAN', $this->vlan);
    }

    public function getOnuMgmtIpAttribute()
    {
        return $this->mgmt_ip_mode !== 'Inactive' ? sprintf('%s - vlan: %s', $this->mgmt_ip_mode, $this->mgmt_ip_vlan) : 'Inactivo';
    }

    public function getOnuSignalAttribute()
    {
        return $this->status === 'Online' ? sprintf('%s dBm / %s dBm', $this->signal_1490, $this->signal_1310) : '-';
    }

    public function getOnuPonTypeAttribute()
    {
        return in_array($this->pon_type, ['gpon', 'xgpon', 'xgspon']) ? 'GPON' : 'EPON';
    }

    public function getConfiguredAttribute()
    {
        return isset($this->board) && isset($this->port);
    }

    public function getCapabilitiesAttribute()
    {
        $capabilities = $this->typeOnu?->capability ?? null;
        if ($capabilities) {
            $capabilities = explode('/', $capabilities);
        }
        return $capabilities;
    }
}
