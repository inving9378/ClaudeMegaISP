<?php

namespace App\Jobs\Mikrotik;

use App\Models\Router;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Mikrotik;
use App\Http\Traits\RouterConnection;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use Carbon\Carbon;

class MikrotikRulesJob implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels,
        RouterConnection;

    protected $mikrotik;
    protected $router;
    protected $meganet_config_ip_address;
    protected $mikrotik_config_server_pppoe_name;
    protected $mikrotik_config_server_pppoe_interface;
    protected $mikrotik_config_server_pppoe_mtu;
    protected $mikrotik_config_server_pppoe_mru;
    protected $mikrotik_config_server_pppoe_profile;
    protected $mikrotik_config_server_ppp_profile;
    protected $mikrotik_config_server_ppp_local_address;
    protected $mikrotik_config_server_ppp_remote_address;
    protected $mikrotik_config_server_ppp_bridge;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Mikrotik $mikrotik)
    {
        $this->mikrotik = $mikrotik;
        $this->router = Router::find($mikrotik->router_id);
        $this->meganet_config_ip_address = $this->router->mikrotikconfig->meganet_config_ip_address;
        $this->mikrotik_config_server_pppoe_name = $this->router->mikrotikconfig->mikrotik_config_server_pppoe_name;
        $this->mikrotik_config_server_pppoe_interface = $this->router->mikrotikconfig->mikrotik_config_server_pppoe_interface;
        $this->mikrotik_config_server_pppoe_mtu = $this->router->mikrotikconfig->mikrotik_config_server_pppoe_mtu;
        $this->mikrotik_config_server_pppoe_mru = $this->router->mikrotikconfig->mikrotik_config_server_pppoe_mru;
        $this->mikrotik_config_server_pppoe_profile = $this->router->mikrotikconfig->mikrotik_config_server_pppoe_profile;
        $this->mikrotik_config_server_ppp_profile = $this->router->mikrotikconfig->mikrotik_config_server_ppp_profile;
        $this->mikrotik_config_server_ppp_local_address = $this->router->mikrotikconfig->mikrotik_config_server_ppp_local_address;
        $this->mikrotik_config_server_ppp_remote_address = $this->router->mikrotikconfig->mikrotik_config_server_ppp_remote_address;
        $this->mikrotik_config_server_ppp_bridge = $this->router->mikrotikconfig->mikrotik_config_server_ppp_bridge;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $device_ip = $this->router->ip_host;
        $command = '/ip/firewall/filter/';

        if ($this->router->type_of_nas == 'Mikrotik') {
            if ($this->mikrotik) {
                $connected = $this->initConnection($this->mikrotik, $device_ip);
                if ($connected) {
                    if ($this->mikrotik->active) {
                        if ($this->mikrotik->rule_address_list_mobility_client) {
                            $this->removeById($connected, $command, $this->getIdByComment($connected, $command, 'MgNet_FORDWARE_DROPEA_INVALIDAS'));
                            $this->addNatRulesMorososRedirct($connected, '/ip/firewall/nat/');
                            $this->addRulesMorososPermitDnsSrc($connected, $command);
                            $this->addRulesMorososPermitDnsDst($connected, $command);
                            $this->addRulesMorososDrop($connected, $command);
                            $this->addRulesIpInvalid($connected, $command);
                            $this->enableProxy($connected, 'yes', $this->mikrotik->port_redirect);
                            $this->addWebProxyAccessIpsPermited($connected, $this->mikrotik->ips_with_comma_permited);
                            $this->addWebProxyAccessIpRedirect($connected, $this->mikrotik->ip_redirect);
                            $this->addWebProxyAccessUrlRedirect($connected, $this->mikrotik->url_redirect);
                        } else {
                            $this->removeById(
                                $connected,
                                '/ip/firewall/nat/',
                                $this->getIdByComment($connected, '/ip/firewall/nat/', 'MgNet_REDIRECT_MOROSOS_TO_WEB_PROXY')
                            );
                            $this->removeById(
                                $connected,
                                $command,
                                $this->getIdByComment($connected, $command, 'MgNet_BLOQUEA_CLIENTES_MOROSOS_ACCEPT_DNS_SRC')
                            );
                            $this->removeById(
                                $connected,
                                $command,
                                $this->getIdByComment($connected, $command, 'MgNet_BLOQUEA_CLIENTES_MOROSOS_ACCEPT_DNS_DST')
                            );
                            $this->removeById(
                                $connected,
                                $command,
                                $this->getIdByComment($connected, $command, 'MgNet_BLOQUEA_CLIENTES_MOROSOS_DROP')
                            );
                            $this->removeById(
                                $connected,
                                '/ip/proxy/access/',
                                $this->getIdByComment($connected, '/ip/proxy/access/', 'MgNet_ACCESS_IP_REDIRECT')
                            );
                            $this->removeById(
                                $connected,
                                '/ip/proxy/access/',
                                $this->getIdByComment($connected, '/ip/proxy/access/', 'MgNet_ACCESS_URL_REDIRECT')
                            );
                            $this->removeById(
                                $connected,
                                $command,
                                $this->getIdByComment($connected, $command, 'MgNet_FORDWARE_DROPEA_INVALIDAS')
                            );
                            $this->removeIpSerparatedbyComma($connected, $this->mikrotik->ips_with_comma_permited);
                            $this->enableProxy($connected, 'no', $this->mikrotik->port_redirect);
                        }

                        if ($this->mikrotik->bloking_rules) {
                            //
                        } else {
                            //
                        }

                        // TODO crear en base de datos campos con los parametros necesarios para crear el servicio PPPOE, PPP y crear vista
                        if (
                            $this->ifAuthorizationAccountingIsPppSecretApiAcounting(
                                $this->router->authorization_accounting
                            )
                        ) {
                            $this->addPpoeProfile($connected, $this->router);
                            $this->addServerPppoe($connected, $this->router);
                            $this->addServerRadius($connected, $this->router, false);
                        }

                        $this->addRulesInputApiAccept($connected, $command, $this->meganet_config_ip_address, $this->mikrotik->port_api);
                        $this->addRulesInputEstablishedRelated(
                            $connected,
                            $command
                        );
                        $this->addRulesForwardEstablishedRelated(
                            $connected,
                            $command
                        );
                        $this->addRulesInputDropInvalid($connected, $command);
                    } else {
                        $this->removeById(
                            $connected,
                            '/ip/firewall/filter/',
                            $this->getIdByComment(
                                $connected,
                                '/ip/firewall/filter/',
                                'MgNet_INPUT_DROPEAR_INVALIDAS'
                            )
                        );
                        $this->removeById(
                            $connected,
                            '/ip/firewall/filter/',
                            $this->getIdByComment(
                                $connected,
                                '/ip/firewall/filter/',
                                'MgNet_INPUT_ESTABLECIDAS_RELACIONADAS'
                            )
                        );
                        $this->removeById(
                            $connected,
                            '/ip/firewall/filter/',
                            $this->getIdByComment(
                                $connected,
                                '/ip/firewall/filter/',
                                'MgNet_FORWARD_ESTABLECIDAS_RELACIONADAS'
                            )
                        );
                        $this->removeById(
                            $connected,
                            $command,
                            $this->getIdByComment(
                                $connected,
                                $command,
                                'MgNet_INPUT_MEGANET_TO_API_ACCEPT'
                            )
                        );
                    }
                    return $this->getVersion($connected);
                } else {
                    //
                }
            }
        }
    }

    public function ifAuthorizationAccountingIsPppSecretApiAcounting($value)
    {
        $value == 'PPP(Secrets)/API Acounting' ? ($v = true) : ($v = false);
        return $v;
    }

    public function addNatRulesMorososRedirct($connected, $command)
    {
        $comment = 'MgNet_REDIRECT_MOROSOS_TO_WEB_PROXY';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, $command, [
                'chain' => 'dstnat',
                'protocol' => 'tcp',
                'dst-port' => '80',
                'action' => 'redirect',
                'to-ports' => $this->mikrotik->port_redirect,
                'src-address-list' => 'MgNet_Morosos',
                'comment' => $comment,
            ]);
        }
    }

    public function addRulesMorososPermitDnsSrc($connected, $command)
    {
        $comment = 'MgNet_BLOQUEA_CLIENTES_MOROSOS_ACCEPT_DNS_SRC';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'forward',
                'action' => 'accept',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => 'udp',
                'src-port' => null,
                'dst-port' => '53',
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => 'MgNet_Morosos',
                'dst-address-list' => null,
                'connection-state' => null,
                'comment' => $comment,
            ]);
        }
    }

    public function addRulesMorososPermitDnsDst($connected, $command)
    {
        $comment = 'MgNet_BLOQUEA_CLIENTES_MOROSOS_ACCEPT_DNS_DST';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'forward',
                'action' => 'accept',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => 'udp',
                'src-port' => null,
                'dst-port' => '53',
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => 'MgNet_Morosos',
                'connection-state' => null,
                'comment' => $comment,
            ]);
        }
    }

    public function addRulesMorososDrop($connected, $command)
    {
        $comment = 'MgNet_BLOQUEA_CLIENTES_MOROSOS_DROP';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'forward',
                'action' => 'drop',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => null,
                'src-port' => null,
                'dst-port' => null,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => 'MgNet_Morosos',
                'dst-address-list' => null,
                'connection-state' => null,
                'comment' => $comment,
            ]);
        }
    }



    //  TODO REVISAR Y DESPUES IMPLEMENTAR LAS INVALIDAS
    public function addRulesIpInvalid($connected, $command)
    {
        $comment = 'MgNet_FORDWARE_DROPEA_INVALIDAS';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'forward',
                'action' => 'drop',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => null,
                'src-port' => null,
                'dst-port' => null,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => 'SpLBL_blocked',
                'dst-address-list' => null,
                'connection-state' => 'invalid',
                'comment' => $comment,
            ]);
        }
    }

    public function addRulesInputEstablishedRelated($connected, $command)
    {
        $comment = 'MgNet_INPUT_ESTABLECIDAS_RELACIONADAS';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'input',
                'action' => 'accept',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => null,
                'src-port' => null,
                'dst-port' => null,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => null,
                'connection-state' => 'established,related',
                'comment' => $comment,
            ]);
        }
    }


    public function addRulesInputDropInvalid($connected, $command)
    {
        $comment = 'MgNet_INPUT_DROPEAR_INVALIDAS';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'input',
                'action' => 'drop',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => null,
                'src-port' => null,
                'dst-port' => null,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => null,
                'connection-state' => 'invalid',
                'comment' => $comment,
            ]);
        }
    }

    public function addRulesInputDorpRest($connected, $command)
    {
        $comment = 'MgNet_INPUT_DROPEA_EL_RESTO';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'input',
                'action' => 'drop',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => null,
                'src-port' => null,
                'dst-port' => null,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => null,
                'connection-state' => null,
                'comment' => $comment,
            ]);
        }
    }

    public function addRulesForwardEstablishedRelated($connected, $command)
    {
        $comment = 'MgNet_FORWARD_ESTABLECIDAS_RELACIONADAS';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, '/ip firewall filter ', [
                'chain' => 'forward',
                'action' => 'accept',
                'src-address' => null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => null,
                'src-port' => null,
                'dst-port' => null,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => null,
                'connection-state' => 'established,related',
                'comment' => $comment,
            ]);
        }
    }

    public function removeIpSerparatedbyComma($connected, $IpSerparatedbyComma)
    {
        $ips = explode(',', $IpSerparatedbyComma);
        foreach ($ips as $ip) {
            $this->removeById(
                $connected,
                '/ip/proxy/access/',
                $this->getIdByComment(
                    $connected,
                    '/ip/proxy/access/',
                    'MgNet_ACCESS_IP_PERMITED-' . $ip
                )
            );
        }
    }
}
