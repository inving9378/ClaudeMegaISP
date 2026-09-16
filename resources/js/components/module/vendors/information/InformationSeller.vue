<template>
    <div class="row" v-if="activeTab === '#navs-pills-justified-information'">
        <div class="col-md-6">
            <div class="card mb-4 h-100">
                <h5 class="card-header">Información Principal</h5>
                <div class="card-body demo-vertical-spacing demo-only-element">
                    <div class="px-4">
                        <div class="mb-3">
                            <label class="form-label"
                                >Nombre(s): {{ seller.name }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Apellido Paterno:
                                {{ seller.father_last_name }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Apellido Materno:
                                {{ seller.mother_last_name }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Correo electrónico: {{ seller.email }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Teléfono: {{ seller.phone }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Dirección: {{ seller.address }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Municipio:
                                {{ seller.city_municipality }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Estado: {{ seller.state_country }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Código Postal: {{ seller.code_postal }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >RFC: {{ seller.rfc }}</label
                            >
                        </div>
                        <hr />
                        <form @submit.prevent="update">
                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select
                                    v-model="data.type_id"
                                    class="form-select"
                                    aria-label="Default select example"
                                >
                                    <option
                                        v-for="typeS in typesSeller"
                                        :key="typeS.id"
                                        :value="typeS.id"
                                    >
                                        {{ typeS.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select
                                    v-model="data.status_id"
                                    class="form-select"
                                    aria-label="Default select example"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status.id"
                                        :value="status.id"
                                    >
                                        {{ status.name }}
                                    </option>
                                </select>
                            </div>
                            <hr />
                            <div class="d-md-flex justify-content-center mt-2">
                                <button type="submit" class="btn btn-primary">
                                    Actualizar Información
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4 h-100">
                <h5 class="card-header">Formato de credencial</h5>
                <div
                    class="card-body demo-vertical-spacing demo-only-element d-flex flex-column align-items-center justify-content-center"
                >
                    <div
                        class="card background-credential-front m-2 p-2 credential"
                        :style="{
                            backgroundImage:
                                imgFront && imgFront.name
                                    ? `url(/credencial/${imgFront.name})`
                                    : 'url(/images/gafete-front.png)',
                        }"
                    >
                        <div class="card-body">
                            <img
                                class="image-logo"
                                :src="
                                    imgLogo && imgLogo.name
                                        ? `/credencial/${imgLogo.name}`
                                        : '/images/logo_meganet.jpg'
                                "
                                alt="logo de credencial"
                            />
                            <div class="d-flex justify-content-center mt-4">
                                <img
                                    class="image-perfil"
                                    :src="
                                        seller.photography
                                            ? `/perfiles/${seller.photography}`
                                            : '/images/perfil.png'
                                    "
                                />
                            </div>
                            <h3 class="name-credential text-uppercase">
                                {{ seller.name }}
                                {{ seller.father_last_name }}
                                {{ seller.mother_last_name }}
                            </h3>
                            <h3 class="title-user">Vendedor</h3>
                            <p class="credential-text">
                                Teléfono: {{ seller.phone }}
                            </p>
                            <p class="credential-text">
                                Correo electrónico: {{ seller.email }}
                            </p>
                            <p class="credential-text">RFC: {{ seller.rfc }}</p>
                        </div>
                    </div>

                    <div
                        class="card background-credential-back m-2 p-2 example credential"
                        :style="{
                            backgroundImage:
                                imgBack && imgBack.name
                                    ? `url(/credencial/${imgBack.name})`
                                    : 'url(/images/gafete-back.png)',
                        }"
                    >
                        <div class="card-body example">
                            <img
                                class="image-logo"
                                :src="
                                    imgLogo && imgLogo.name
                                        ? `/credencial/${imgLogo.name}`
                                        : '/images/logo_meganet.jpg'
                                "
                                alt="logo de credencial"
                            />
                            <div class="text-back">
                                <h5 class="text-center">
                                    Dirección de la empresa:
                                </h5>
                                <p class="credential-text-black">
                                    Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc.
                                    Ex Hacienda Santa Ines Nextlalpan Edo de
                                    Mexico, CP 55796.
                                </p>

                                <p class="text-center signature"></p>
                                <p class="text-center firm">Firma</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a
                            class="btn btn-primary"
                            :href="'/vendedores/' + props.id + '/pdf'"
                        >
                            <i class="fas fa-file-download m-1"></i>
                            Descargar credencial
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" v-if="activeTab === '#navs-pills-justified-information' && hasDocumentPermission">
        <div class="col-md-12">
            <div class="card mb-4 mt-4 tc-card">
                <h5 class="card-header tc-cardhead">Documentos</h5>
                <div class="card-body">
                    <div v-if="!colaboradorId" class="text-muted small text-center py-3">
                        No se pudo cargar el expediente de documentos.
                    </div>
                    <talento-expediente-documentos
                        v-else
                        :colaborador-id="colaboradorId"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, ref, reactive, onMounted, watch } from "vue";
import {
    getById,
    updateSeller,
    getImageFront,
    getImageBack,
    getImageLogo,
    getStatusSeller,
    getTypeSeller,
    getExpedienteVendedor,
} from "../helper/request.js";
import Swal from "sweetalert2";
import { activeTab } from "../../sellers/comun_variables.js";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

const props = defineProps({
    id: Number,
});

const seller = ref([]);
const data = reactive({
    type_id: null,
    status_id: null,
});

const credential = ref({
    image: "",
    type: "",
});

const imgFront = ref([]);
const imgBack = ref([]);
const imgLogo = ref([]);

const typesSeller = ref([]);
const statuses = ref([]);

const isLoaded = ref(false);

// Card "Documentos" del expediente (item #9990360, Hijo E3)
const hasPermission = reactive({ data: new Permission({}) });
const hasDocumentPermission = ref(false);
const colaboradorId = ref(null);

const loadDocumentAccess = async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    hasDocumentPermission.value = hasPermission.data.canView(
        "talento.expediente.documentos.ver"
    );
    if (hasDocumentPermission.value) {
        try {
            colaboradorId.value = await getExpedienteVendedor(props.id);
        } catch {
            colaboradorId.value = null;
        }
    }
};

onMounted(async () => {
    if (activeTab.value === "#navs-pills-justified-information") {
        isLoaded.value = true;
        seller.value = await getById(props.id);
        data.type_id = seller.value.type_id;
        data.status_id = seller.value.status_id;
        imgFront.value = await getImageFront();
        imgBack.value = await getImageBack();
        imgLogo.value = await getImageLogo();

        typesSeller.value = await getTypeSeller();
        statuses.value = await getStatusSeller();
        await loadDocumentAccess();
    }
});

watch(activeTab, async () => {
    if (activeTab.value === "#navs-pills-justified-information" && !isLoaded.value) {
        seller.value = await getById(props.id);
        data.type_id = seller.value.type_id;
        data.status_id = seller.value.status_id;
        imgFront.value = await getImageFront();
        imgBack.value = await getImageBack();
        imgLogo.value = await getImageLogo();

        typesSeller.value = await getTypeSeller();
        statuses.value = await getStatusSeller();
        await loadDocumentAccess();
    }
});

const update = async () => {
    try {
        const response = await updateSeller(props.id, data);
        Swal.fire("¡Actualizado!", response.message, "success");
    } catch (error) {
        console.log(error);
        Swal.fire("Error", "Hubo un error al actualizar el vendedor", "error");
    }
};
</script>

<style scoped>
/* Lista de documentos (talento-expediente-documentos, COMPARTIDA con el Portal de
   Colaborador de Talento). :deep() la estiliza como zebra de tabla SOLO cuando se
   renderiza aquí, dentro de la ficha de Vendedores — no afecta a Talento. */
:deep(.documentos-table) {
    border-radius: 12px;
    overflow: hidden;
}
:deep(.documentos-table thead th) {
    border: none;
    background: var(--tc-thead, #e4e7eb);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 12px 16px;
}
:deep(.documentos-table tbody td) {
    border: none;
    border-bottom: 1px solid var(--tc-line, #e5e8ec);
    background: transparent;
    padding: 12px 16px;
}
:deep(.documentos-table tbody tr:last-child td) {
    border-bottom: none;
}
:deep(.documentos-table tbody tr:nth-child(even) td) {
    background: var(--tc-zebra, #f5f6f8);
}
:deep(.documentos-table tbody tr:hover td) {
    background: var(--tc-line, #e5e8ec);
}

/* Botones de acción (Ver/Imprimir/Firmar/Completar) — Bootstrap default (azul/gris/rojo
   genéricos) desentonaba con el tema Torre. Mismo mecanismo :deep(), mismos tokens/tc-btn. */
:deep(.documentos-table .btn) {
    border-radius: 8px;
    font-weight: 600;
    border: 1px solid transparent;
    transition: filter 0.15s, background 0.15s;
}
:deep(.documentos-table .btn:hover) {
    filter: brightness(1.06);
}
:deep(.documentos-table .btn-primary) {
    background: var(--tc-accent, #0d9488);
    border-color: var(--tc-accent, #0d9488);
    color: #fff;
}
:deep(.documentos-table .btn-secondary) {
    background: transparent;
    border-color: var(--tc-line, #d3d8de);
    color: var(--tc-ink, #1a2230);
}
:deep(.documentos-table .btn-danger) {
    background: transparent;
    border-color: var(--tc-bad, #dc2626);
    color: var(--tc-bad, #dc2626);
}
:deep(.documentos-table .btn-danger:hover) {
    background: var(--tc-bad, #dc2626);
    color: #fff;
}

/* Badge de estado (Completo/Pendiente/Pendiente de firma) — el bg-success/warning/danger
   sólido de Bootstrap se veía plano; pill suave con los mismos tonos del tema. */
:deep(.documentos-table .badge) {
    font-weight: 600;
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 999px;
}
:deep(.documentos-table .badge.bg-success) {
    background: rgba(22, 163, 74, 0.16) !important;
    color: var(--tc-ok, #16a34a) !important;
}
:deep(.documentos-table .badge.bg-warning) {
    background: rgba(217, 119, 6, 0.16) !important;
    color: var(--tc-warn, #d97706) !important;
}
:deep(.documentos-table .badge.bg-danger) {
    background: rgba(220, 38, 38, 0.16) !important;
    color: var(--tc-bad, #dc2626) !important;
}

.credential {
    width: 100%;
    max-width: 380px;
    aspect-ratio: 380 / 600;
    margin-left: auto;
    margin-right: auto;
    overflow: hidden;
}

.background-credential-front {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

.background-credential-back {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

.image-logo {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    object-fit: contain;
    height: 18%;
    width: 100%;
}

.image-perfil {
    border-radius: 50%;
    width: clamp(70px, 30%, 150px);
    height: clamp(70px, 30%, 150px);
    border: 5px solid #004790;
    margin-bottom: 10px;
}

.title-credential {
    font-size: 1rem;
    font-weight: bold;
    color: #004790;
    text-align: center;
}

.title-user {
    font-size: clamp(0.55rem, 2.2vw, 0.8rem);
    color: #fff;
    text-align: center;
    text-transform: uppercase;
}

.name-credential {
    margin-top: 10px;
    font-size: clamp(0.8rem, 3.4vw, 1.3rem);
    font-weight: bold;
    color: #fff;
    text-align: center;
    overflow-wrap: break-word;
    word-break: break-word;
    padding: 0 8px;
}

.credential-text {
    font-size: clamp(0.62rem, 2.8vw, 1.1rem);
    color: #fff;
    text-align: center;
    overflow-wrap: break-word;
    word-break: break-word;
    padding: 0 10px;
}

.credential-text-black {
    font-size: clamp(0.6rem, 2.5vw, 1rem);
    color: #504f4f;
    text-align: justify;
    overflow-wrap: break-word;
    word-break: break-word;
}

.text-back {
    margin-top: 30px;
    overflow: auto;
}

.signature {
    margin-top: 150px;
    border-bottom: 1px solid black;
    width: 250px;
    margin-left: auto;
    margin-right: auto;
}

.firm {
    font-size: 1.5rem;
    font-weight: bold;
}

@media (max-width: 1600px) {
    .title-credential {
        font-size: 1.1rem;
    }

    .name-credential {
        padding-top: 50px;
    }

    .signature {
        margin-top: 100px;
        border-bottom: 1px solid black;
        width: 150px;
        margin-left: auto;
        margin-right: auto;
    }

    .firm {
        font-size: 1.2rem;
        font-weight: bold;
    }
}

@media (max-width: 900px) {
    .title-credential {
        font-size: 1rem;
    }

    .name-credential {
        padding-top: 0;
        margin-top: 0;
    }

    .text-back {
        margin-top: 5px;
        overflow: auto;
    }

    .signature {
        margin-top: 20px;
        border-bottom: 1px solid black;
        width: 100px;
        margin-left: auto;
        margin-right: auto;
    }

    .firm {
        font-size: 1rem;
        font-weight: bold;
    }

    .signature {
        margin-top: 0;
    }
}

@media (max-width: 480px) {
    .title-credential {
        font-size: 0.8rem;
    }

    .signature {
        margin-top: 50px;
    }
}
</style>
