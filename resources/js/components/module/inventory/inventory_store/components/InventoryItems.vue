<template>
    <div class="d-flex flex-wrap justify-between gap-2 mb-2">
        <div class="row col-9">
            <div class="col-6">
                <SelectComponentWithCheckbox
                    :property="{
                        field: 'inventory_item_type_id',
                        label: 'Tipo de Artículo',
                        class_col: '',
                        search: {
                            model: 'App\\Models\\InventoryItemType',
                            id: `id`,
                            text: 'name',
                        },
                        module_id: module_id,
                    }"
                    @change="clearError('inventory_item_type_id')"
                    :modelValue="[]"
                    :errors="dataForm.data.errors"
                    @update-field="setFilter"
                />
            </div>
            <div class="col-6">
                <SelectComponentWithCheckbox
                    :property="{
                        field: 'user_id',
                        label: 'Asignado a',
                        class_col: '',
                        search: {
                            model: 'App\\Models\\User',
                            id: `id`,
                            text: 'name',
                            scope: 'userHasItem',
                        },
                        module_id: module_id,
                    }"
                    @change="clearError('user_id')"
                    :modelValue="[]"
                    :errors="dataForm.data.errors"
                    @update-field="setFilter"
                />
            </div>
        </div>
    </div>
    <Datatable
        module="inventory/inventory_item_stock"
        model="InventoryItemStock"
        list="Listado de Articulos"
        @table="table"
        :editButton="{ modal: 'crudinventoryitem' }"
        :persistentFilters="filterStoreId"
        :buttons="getButtonDatatable()"
    ></Datatable>

    <div
        class="modal fade"
        id="crudinventoryitem"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <InventoryItemCrud
                    :action="action"
                    :key="reloadCrud"
                    @close-modal="closeModal"
                ></InventoryItemCrud>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalchange_item_store"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Mover Artículo</h6>
                </div>
                <InventoryMovementAll
                    @close-modal="closeModal"
                    :filter_store="filterStoreId"
                    :key="reloadCrud"
                >
                </InventoryMovementAll>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalchange_item_stock"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Aumentar o Disminuir Stock</h6>
                </div>
                <InventoryIncrementDecrementStock @close-modal="closeModal" :key="reloadCrud">
                </InventoryIncrementDecrementStock>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalcrudstore_zone"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Agregar Zona</h6>
                </div>
                <StoreZoneCrud
                    @close-modal="closeModal"
                    action="/inventory/store_zone/add"
                    :key="reloadCrud"
                ></StoreZoneCrud>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="modal_media_item"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Galeria de Imagenes</h6>
                </div>
                <MediaItem
                    :url_base="url_base"
                    @close-modal="closeModal"
                    :key="reloadCrud"
                ></MediaItem>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import Datatable from "../../../../base/shared/Datatable.vue";
import InventoryItemCrud from "../../inventory_item_stock/InventoryItemCrud.vue";
import DatatableHelper from "../../../../../helpers/datatableHelper";
import Form from "../../../../../helpers/Form";
import SelectComponentWithCheckbox from "../../../../../shared/SelectComponentWithCheckbox.vue";
import { filters } from "../../../../../helpers/filters";
import SelectComponent from "../../../../../shared/SelectComponent.vue";
import InputNumber from "../../../../../shared/InputNumber.vue";
import InventoryMovementAll from "../../component/InventoryMovementAll.vue";
import InventoryIncrementDecrementStock from "../../component/InventoryIncrementDecrementStock.vue";
import { idItem } from "../../comun_variables";
import StoreZoneCrud from "../../store_zone/StoreZoneCrud.vue";
import Swal from "sweetalert2";
import MediaItem from "../../inventory_item_media/MediaItem.vue";

export default {
    name: "InventoryItems",
    props: {
        store_id: String | Number,
        module_id: {
            type: String,
            default: null,
        },
        url_base: String
    },
    components: {
        MediaItem,
        Datatable,
        InventoryItemCrud,
        SelectComponentWithCheckbox,
        SelectComponent,
        InputNumber,
        InventoryMovementAll,
        InventoryIncrementDecrementStock,
        StoreZoneCrud,
    },
    setup(props, { emit }) {
        onMounted(() => {});

        const title = ref("Crear Articulo");
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref("/inventory/inventory_item/add");
        const reloadCrud = ref(true);
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            $(document).on("click", ".uil-pen-modal", function () {
                let id = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(id, modal);
            });
            $(document).on("click", ".change_item_store", function () {
                let id = $(this).parent().attr("id-item");
                showChangeStoreModal(id);
            });
            $(document).on("click", ".change_item_stock", function () {
                let id = $(this).parent().attr("id-item");
                showChangeItemStock(id);
            });
            $(document).on("click", ".change_zone", function () {
                const $a = $(this).closest("a");
                showEditZone({
                    inventory_store_id: $a.data("inventory-store-id"),
                    store_zone_id: $a.data("store-zone-id"),
                    item_id: $a.data("item-id"),
                });
            });


            $(document).on("click", ".inventory_item_image", function () {
                let id = $(this).parent().attr("id-item");
                showMediaItem(id);
            });
        });

        const closeModal = () => {
            $("#crudinventoryitem").modal("hide");
            $(`#modalchange_item_store`).modal("hide");
            $(`#modalchange_item_stock`).modal("hide");
            $(`#modalcrudstore_zone`).modal("hide");
            $(`#modal_media_item`).modal("hide");

            reloadCrud.value = !reloadCrud.value;
            title.value = "Crear Articulo";
            action.value = "/inventory/inventory_item/add";
            datatable.table.reload();
        };

        const showEditModal = (id) => {
            $("#crudinventoryitem").modal("show");
            $(`#modalassign_item_to_user`).modal("hide");
            $(`#modalchange_item_store`).modal("hide");
            $(`#modalchange_item_stock`).modal("hide");
            $(`#modalcrudstore_zone`).modal("hide");
            $(`#modal_media_item`).modal("hide");
            title.value = "Editar Articulo";
            action.value = `/inventory/inventory_item/update/${id}`;
        };

        const showChangeStoreModal = () => {
            $(`#modalchange_item_store`).modal("show");
        };
        const showChangeItemStock = (id) => {
            idItem.value = id;
            $(`#modalchange_item_stock`).modal("show");
        };

        const showStoreZoneModal = () => {
            $(`#modalcrudstore_zone`).modal("show");
        };

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const showMediaItem = (id) => {
            idItem.value = id;
            $(`#modal_media_item`).modal("show");
        };

        const setFilter = (obj) => {
            filters.value = {
                ...filters.value,
                [obj.field]: obj.value._value, // Asigna dinámicamente el valor al campo especificado
            };
        };

        const getButtonDatatable = () => {
            let buttons = {};

            buttons.view_zones = {
                class: "btn btn-outline-primary waves-effect waves-light me-3",
                href: `/inventory/store_zone/show-zones-by-store/${props.store_id}`,
                id: `btn-show-zone-${props.store_id}`,
                target: "_blank",

                text: "Ver Zonas",
            };

            buttons.add_zone = {
                class: "btn btn-outline-primary waves-effect waves-light me-3",
                href: "javascript:void(0)",
                id: "modalcrudstore_zone_button",
                dataBsTarget: "#modalcrudstore_zone",
                dataBsToogle: "modal",
                text: "Agregar Zona",
            };

            buttons.add_item = {
                class: "btn btn-outline-primary waves-effect waves-light me-3",
                href: "javascript:void(0)",
                id: "buttonmodaluploaddocument",
                dataBsTarget: "#crudinventoryitem",
                dataBsToogle: "modal",
                text: "Agregar Articulo",
            };

            buttons.change_item_store = {
                class: "btn btn-outline-primary waves-effect waves-light",
                href: "javascript:void(0)",
                id: "change_item_store",
                dataBsTarget: "#modalchange_item_store",
                dataBsToogle: "modal",
                text: "Realiza un Movimiento",
            };

            return buttons;
        };

        const filterStoreId = ref({
            inventory_store_id: [props.store_id],
        });
        const showEditZone = async ({
                                        inventory_store_id,
                                        store_zone_id,
                                        item_id,
                                    }) => {
            Swal.fire({
                title: "Editar zona",
                icon: "info",
                showCancelButton: true,
                confirmButtonText: "Guardar cambios",
                cancelButtonText: "Cancelar",
                focusConfirm: false,
                customClass: {
                    popup: "swal-zone-popup",
                    confirmButton: "btn btn-primary",
                    cancelButton: "btn btn-outline-secondary ms-2",
                },
                buttonsStyling: false,
                html: `
            <div class="text-start">
                <label class="form-label fw-semibold mb-1">
                    Nueva zona *
                </label>

                <select id="swal-zone" style="width:100%"></select>

                <small class="text-muted d-block mt-1">
                    Desplázate para cargar más zonas
                </small>
            </div>
        `,
                didOpen: async () => {
                    const confirmBtn = Swal.getConfirmButton();
                    confirmBtn.disabled = true;

                    const $select = $('#swal-zone');

                    $select.select2({
                        dropdownParent: $('.swal2-popup'),
                        width: '100%',
                        placeholder: 'Selecciona una zona',
                        allowClear: true,
                        minimumResultsForSearch: Infinity,

                        ajax: {
                            url: '/inventory/store_zone/search',
                            delay: 200,
                            data: params => ({
                                inventory_store_id,
                                page: params.page || 1,
                                per_page: 50,
                            }),
                            processResults: (response, params) => {
                                params.page = params.page || 1;

                                return {
                                    results: response.data.map(z => ({
                                        id: z.id,
                                        text: z.name,
                                    })),
                                    pagination: {
                                        more: response.pagination.has_more,
                                    },
                                };
                            },
                            cache: true,
                        },
                    });

                    // Precargar zona actual
                    if (store_zone_id) {
                        try {
                            const zone = await axios
                                .get(`/inventory/store_zone/get-by-id/${store_zone_id}`)
                                .then(r => r.data);

                            const option = new Option(
                                zone.name,
                                zone.id,
                                true,
                                true
                            );

                            $select.append(option).trigger('change');
                            confirmBtn.disabled = false;
                        } catch (e) {
                            console.warn('No se pudo precargar la zona actual');
                        }
                    }

                    $select.on('change', () => {
                        confirmBtn.disabled = !$select.val();
                    });
                },
                preConfirm: () => {
                    const zone = $('#swal-zone').val();

                    if (!zone) {
                        Swal.showValidationMessage("Debes seleccionar una zona");
                        return false;
                    }

                    return {
                        inventory_store_id,
                        zone_old: store_zone_id,
                        store_zone_id: zone,
                        item_id,
                    };
                },
            }).then(async result => {
                if (!result.isConfirmed) return;
                await axios.post(`/inventory/store_zone/update-zone`, result.value);
                Swal.fire({
                    icon: "success",
                    title: "Zona actualizada",
                    timer: 1200,
                    showConfirmButton: false,
                });
                reload();
            }).catch(error => {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message,
                });
            })
        };

        return {
            filterStoreId,
            title,
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            dataForm,
            setFilter,
            idItem,
            showEditModal,
            showChangeStoreModal,
            showChangeItemStock,
            showStoreZoneModal,
            getButtonDatatable,
        };
    },
};
</script>

<style scoped>
</style>
