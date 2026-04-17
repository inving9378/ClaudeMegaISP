<template>
    <div class="row">
        <div class="col-xxl-4">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm order-2 order-sm-1">
                            <div class="d-flex align-items-start mt-3 mt-sm-0">
                                <div class="flex-shrink-0">
                                    <div class="avatar-xl me-3">
                                        <img
                                            :src="fieldsJson.url_photography"
                                            alt=""
                                            class="img-fluid rounded-circle d-block"
                                        />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div>
                                        <h5 class="font-size-16 mb-1">
                                            {{ fieldsJson.name }}
                                        </h5>
                                        <p class="text-muted font-size-13">
                                            {{ fieldsJson.rol_name ?? "-" }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-auto order-1 order-sm-2">
                            <div
                                class="d-flex align-items-start justify-content-end gap-2"
                            >
                                <div>
                                    <a
                                        :href="`/perfil/editar/${id}`"
                                        class="btn btn-primary"
                                        >Editar</a
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end card body -->
            </div>
        </div>
        <div class="col-xxl-8">
            <div class="card">
                <div class="card-header">
                    <ul
                        class="nav nav-tabs-custom card-header-tabs"
                        id="pills-tab"
                        role="tablist"
                    >
                        <li class="nav-item">
                            <a
                                class="nav-link px-3 active"
                                data-bs-toggle="tab"
                                href="#overview"
                                role="tab"
                                >PERFIL</a
                            >
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link px-3"
                                data-bs-toggle="tab"
                                href="#activity"
                                role="tab"
                                >INFORMACIÓN</a
                            >
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div
                            class="tab-pane active"
                            id="overview"
                            role="tabpanel"
                        >
                            <div class="card-body">
                                <div>
                                    <div class="pb-3">
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Nombre
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            fieldsJson.name ??
                                                            "-"
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Correo
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            fieldsJson.email ??
                                                            "-"
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Usuario
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            fieldsJson.login_user ??
                                                            "-"
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Teléfono
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            !perfilJson
                                                                ? ".."
                                                                : perfilJson.phone
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Ubicación
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            !perfilJson
                                                                ? ".."
                                                                : perfilJson.location
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Fecha de alta
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            fieldsJson.created_at
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Último acceso
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            fieldsJson &&
                                                            fieldsJson.system_user
                                                                ? fieldsJson
                                                                      .system_user
                                                                      .last_access
                                                                : "-"
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-2">
                                                <div>
                                                    <h5 class="font-size-15">
                                                        Último IP de acceso
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-xl">
                                                <div class="text-muted">
                                                    <p class="mb-2">
                                                        {{
                                                            fieldsJson &&
                                                            fieldsJson.system_user
                                                                ? fieldsJson
                                                                      .system_user
                                                                      .last_ip
                                                                : "-"
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end card -->
                        </div>
                        <!-- end tab pane -->

                        <div class="tab-pane" id="activity" role="tabpanel">
                            <h5 class="card-title mb-0">Actividad</h5>
                            <div class="card-body">
                                <div>
                                    <div class="pb-3">
                                        <h5 class="font-size-15">Bio :</h5>
                                        <div class="text-muted">
                                            <p class="mb-2">
                                                Hi I'm Phyllis Gatlin, Lorem
                                                Ipsum is simply dummy text of
                                                the printing and typesetting
                                                industry. Lorem Ipsum has been
                                                the industry's standard dummy
                                                text ever since the 1500s, when
                                                an unknown printer took a galley
                                                of type and scrambled it to make
                                                a type specimen book. It has
                                                survived not only five
                                                centuries, but also the leap
                                                into electronic typesetting,
                                                remaining essentially unchanged.
                                                It was popularised in the 1960s
                                                with the release of Letraset
                                                sheets containing Lorem Ipsum
                                                passages
                                            </p>
                                            <p class="mb-2">
                                                It is a long established fact
                                                that a reader will be distracted
                                                by the readable content of a
                                                page when looking at it has a
                                                more-or-less normal distribution
                                                of letters
                                            </p>
                                            <p>
                                                It will be as simple as
                                                Occidental; in fact, it will be
                                                Occidental. To an English
                                                person, it will seem like
                                                simplified English, as a
                                                skeptical Cambridge friend of
                                                mine told me what Occidental is.
                                                The European languages are
                                                members of the same family.
                                                Their separate existence is a
                                                myth.
                                            </p>

                                            <ul class="list-unstyled mb-0">
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Donec vitae sapien ut
                                                    libero venenatis faucibus
                                                </li>
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Quisque rutrum aenean
                                                    imperdiet
                                                </li>
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Integer ante a consectetuer
                                                    eget
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="pt-3">
                                        <h5 class="font-size-15">
                                            Experience :
                                        </h5>
                                        <div class="text-muted">
                                            <p>
                                                If several languages coalesce,
                                                the grammar of the resulting
                                                language is more simple and
                                                regular than that of the
                                                individual languages. The new
                                                common language will be more
                                                simple and regular than the
                                                existing European languages. It
                                                will be as simple as Occidental;
                                                in fact, it will be Occidental.
                                                To an English person, it will
                                                seem like simplified English, as
                                                a skeptical Cambridge friend of
                                                mine told me what Occidental is.
                                                The European languages are
                                                members of the same family.
                                                Their separate existence is a
                                                myth. For science, music, sport,
                                                etc
                                            </p>

                                            <ul class="list-unstyled mb-0">
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Donec vitae sapien ut
                                                    libero venenatis faucibus
                                                </li>
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Quisque rutrum aenean
                                                    imperdiet
                                                </li>
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Integer ante a consectetuer
                                                    eget
                                                </li>
                                                <li class="py-1">
                                                    <i
                                                        class="mdi mdi-circle-medium me-1 text-success align-middle"
                                                    ></i
                                                    >Phasellus nec sem in justo
                                                    pellentesque
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end card -->
                        </div>
                        <!-- end tab pane -->
                    </div>
                </div>
                <!-- end card body -->
            </div>
        </div>
        <!-- end tab content -->
    </div>
    <!-- end row -->
</template>

<script>
import { onMounted, ref } from "vue";
import { requestPerfilJson } from "./helper/request";

export default {
    name: "Perfil",
    props: {
        id: {
            type: String,
            default: null,
        },
    },
    setup(props, { emit }) {
        const fieldsJson = ref({});
        const perfilJson = ref({});

        onMounted(() => {
            initComponent();
        });

        const initComponent = () => {
            getPerfilJson();
        };

        const getPerfilJson = async () => {
            fieldsJson.value = await requestPerfilJson(props.id);
            perfilJson.value = !fieldsJson.value.perfil
                ? null
                : fieldsJson.value.perfil;
        };

        return {
            fieldsJson,
            perfilJson,
        };
    },
};
</script>

<style scoped></style>
