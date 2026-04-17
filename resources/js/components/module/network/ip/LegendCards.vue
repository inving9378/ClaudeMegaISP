<template>
    <CardNetworkingLeyend iclass="far fa-circle" title="No utilizado" cardClass="spl-legend-primary" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="far fa-circle" title="Utilizado" subCardClass="spl-legend-item-success"/>
    <CardNetworkingLeyend iclass="far fa-circle" title="Ping OK" cardClass="spl-legend-success" subCardClass="spl-legend-item-gray"/>

    <CardNetworkingLeyend iclass="far fa-circle" title="Ping TimeOut" cardClass="spl-legend-warning" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="far fa-circle" title="Ping Error" cardClass="spl-legend-danger" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="far fa-circle" title="Ping Desconocido" cardClass="spl-legend-primary" subCardClass="spl-legend-item-gray"/>

    <CardNetworkingLeyend iclass="fa fa-user" title="Customer" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-wifi" title="Access point" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-database" title="DB Server" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-shield-alt" title="Firewall" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-arrows-alt" title="L2 Device" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-network-wired" title="Router" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-server" title="Linux Server" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-server" title="Windows Server" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-print" title="Printer" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-phone-alt" title="VoIP Device" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-desktop" title="Workstation" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="fas fa-tag" title="Other" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>
    <CardNetworkingLeyend iclass="far fa-circle" title="Nunguno" cardClass="alert-gray" subCardClass="spl-legend-item-gray"/>

</template>

<script>
import CardNetworkingLeyend from "../../../base/card/networking/CardNetworkingLeyend";
export default {
    name: "LegendCards",
    components: {CardNetworkingLeyend}
}
</script>

<style scoped>

</style>
