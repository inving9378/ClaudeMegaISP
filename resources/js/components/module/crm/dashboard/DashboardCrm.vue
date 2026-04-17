<template>
    <div>
        <!--     Section of target   -->
        <div class="row">
            <tarjet-ticket
                icon="bx bx-id-card"
                label="Mis potenciales"
                value="12"
                link="/internet"
            ></tarjet-ticket>
            <tarjet-ticket
                icon="bx bx-menu-alt-left"
                label="Mis citas"
                value="137"
                link="/voz"
            ></tarjet-ticket>
            <tarjet-ticket
                icon="uil-comment-alt-verify"
                label="Mis ofertas"
                value="127"
                link="/custom"
            ></tarjet-ticket>
            <tarjet-ticket
                icon="bx bx-user"
                label="Todos potenciales"
                value="11"
                link="/paquetes"
            ></tarjet-ticket>
        </div>
        <!--     End Section of target   -->

        <!--    Section of Map -->
        <div class="row">
            <div class="col-xl-6">
                <!--  End Section of  map -->
                <google-map></google-map>
            </div>
            <!--     Section of  Recient activities -->
            <div class="col-xl-6">
                <recent-activity lista=""></recent-activity>
            </div>
            <!--     End Section of Assined ticket  -->
        </div>

        <!--     Section of  Assined ticket to admin-->
        <!--     End Section of Assined ticket  -->
    </div>
</template>

<script>
import TarjetTicket from "../../tickets/component/TarjetTicket";
import RecentActivity from "../../tickets/component/RecentActivity";
import AssignedTicket from "../../tickets/component/AssignedTicket";
import ListAssignedTicket from "../../tickets/component/ListAssignedTicket";
import GoogleMap from "../../../base/googlemap/GoogleMap";

export default {
    name: "DashboardCrm",
    components: {
        TarjetTicket,
        RecentActivity,
        AssignedTicket,
        ListAssignedTicket,
        GoogleMap,
    },
    props: {
        assign_tickets_columns: String,
        assign_tickets_columns_admin: String,
    },
};
</script>

<style scoped></style>
