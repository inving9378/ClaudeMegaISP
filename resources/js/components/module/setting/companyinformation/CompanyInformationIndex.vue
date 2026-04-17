<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-white" style="background-color: #1877F2;">
                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <h3 class="mb-0 text-white"><b>Información de la Empresa</b></h3>
                        <button
                            v-if="
                                hasPermission.data.canView(
                                    'company_information_edit_company_information'
                                )
                            "
                            class="btn btn-light btn-sm"
                            @click="editCompanyInformation"
                        >
                            Editar Información
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="company-info">
                        <div class="row">
                            <div class="col-md-12" v-if="dataCompany">
                                <div class="info-item">
                                    <strong>Nombre:</strong>
                                    <span>{{ dataCompany.company_name }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Email:</strong>
                                    <span>{{ dataCompany.email }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>País:</strong>
                                    <span>{{ dataCompany.country }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Calle:</strong>
                                    <span>{{ dataCompany.company_street }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Número Exterior:</strong>
                                    <span>{{ dataCompany.company_external_number }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Número Interior:</strong>
                                    <span>{{ dataCompany.company_internal_number }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Estado:</strong>
                                    <span>{{ dataCompany.state_name }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Municipio:</strong>
                                    <span>{{
                                        dataCompany.municipality_name
                                    }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Colonia:</strong>
                                    <span>{{ dataCompany.colony_name }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Código Postal:</strong>
                                    <span>{{
                                        dataCompany.company_postal_code
                                    }}</span>
                                </div>
                                <div class="info-item">
                                    <strong
                                        >Teléfono de atención al
                                        cliente:</strong
                                    >
                                    <span>{{
                                        dataCompany.atention_client_phone
                                    }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>IVA:</strong>
                                    <span>{{ dataCompany.iva }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>RFC:</strong>
                                    <span>{{ dataCompany.rfc }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Bank Account:</strong>
                                    <span>{{ dataCompany.bank_account }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Bank Name:</strong>
                                    <span>{{ dataCompany.bank_name }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Cominion Partner:</strong>
                                    <span>{{
                                        dataCompany.cominion_partner
                                    }}</span>
                                </div>
                                <div class="info-item d-flex">
                                    <strong>Logo:</strong>
                                    <span style="display: block; width: 250px">
                                        <img
                                            :src="`${imgbase}/${url_logo}`"
                                            class="img_legend_task"
                                        />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";

import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

export default {
    name: "CompanyInformationIndex",
    components: {},
    props: {
        imgbase: String,
    },
    setup(props) {
        const hasPermission = reactive({
            data: new Permission({}),
        });

        const dataCompany = ref();
        const url_logo = ref();

        onMounted(async () => {
            getDataCompany();
            hasPermission.data = new Permission(await allViewHasPermission());
            showLoading("showTextDef");
            hideLoading();
        });

        const getDataCompany = async () => {
            await axios["post"](
                `/configuracion/company-information/get-data-company`
            ).then((response) => {
                dataCompany.value = response.data;
                let logoPath = dataCompany.value.url_logo;
                url_logo.value = logoPath.replace("public", "/storage");
            });
        };

        const editCompanyInformation = () => {
            window.location.href =
                "/configuracion/company-information/editar/1";
        };

        return {
            hasPermission,
            dataCompany,
            editCompanyInformation,
            url_logo
        };
    },
};
</script>

<style scoped>
.card {
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header {
    font-size: 1.25rem;
    font-weight: bold;
}

.card-header.bg-primary {
    background-color: #007bff;
    color: #fff;
}

.card-body {
    padding: 20px;
}

.company-info .info-item {
    margin-bottom: 15px;
}

.company-info .info-item strong {
    font-weight: bold;
    margin-right: 10px;
    color: #333;
}

.company-info .info-item span {
    font-size: 1rem;
    color: #555;
}

.btn-light {
    color: #007bff;
    background-color: #f8f9fa;
    border: 1px solid #007bff;
}

.btn-light:hover {
    background-color: #007bff;
    color: #fff;
}

.shadow-low {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
