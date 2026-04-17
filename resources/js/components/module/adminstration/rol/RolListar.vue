<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary waves-effect waves-light ms-auto" @click="reload">
            Refrescar
        </button>
        <button type="button" class="btn btn-outline-primary waves-effect waves-light"
                data-bs-toggle="modal" data-bs-target="#crudrole">Agregar
        </button>
    </div>
    <Datatable
        module="administracion/rol"
        model="Role"
        list="Listado de Roles"
        @table="table"
        :buttonsInsideDatatable="buttons"
    ></Datatable>

    <div
        class="modal fade"
        id="crudrole"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <RolCrud
                    :action="action"
                    @close-modal="closeModal"
                ></RolCrud>
            </div>
        </div>
    </div>

    <div class="modal fade bs-example-modal-lg"
         tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true"
         id="permissionrole"
         data-backdrop="static"
         data-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Cambiar Permisos del Rol</h6>
                </div>
                <RolPermission
                    v-if="rol"
                    :rol="rol"
                    @close-modal="closeModal"
                ></RolPermission>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
</template>

<script>

import Datatable from "../../../base/shared/Datatable";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import RolCrud from "./RolCrud";
import RolPermission from "./RolPermission";

export default {
    name: "RolListar",
    components: {RolCrud, RolPermission, Datatable},
    setup(props) {
        const title = ref('Crear Rol');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/administracion/rol/add');
        const rol = ref();
        const buttons = [];

        onMounted(() => {
            addButtons()
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
            $(document).on("click", ".set-permission", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showSetPermissionModal(idItem, modal);
            });
        })

        const closeModal = (modal) => {
            if (modal == 'crudrole'){
                $('#crudrole').modal('hide');
                title.value = 'Crear Rol';
                action.value = '/administracion/rol/add';
                datatable.table.reload();
            }else{
                $('#permissionrole').modal('hide');
            }
        };

        const showEditModal = (idItem, modal) => {
            $(`#${modal}`).modal('show');
            title.value = 'Editar Rol';
            action.value = `/administracion/rol/update/${idItem}`;
        };

        const showSetPermissionModal = (idItem, modal) => {
            $(`#${modal}`).modal('show');
            rol.value = idItem;
        }

        const reload = () => {
            datatable.table.reload();
        }

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        }

        const addButtons = () => {
            buttons.value = [
                {
                    modal:
                        {
                            iclass: 'far fa-edit uil-pen-modal',
                            ref: 'crudrole',
                            title: 'Editar',
                            class: 'mr-2'
                        }
                },
                {
                    modal:
                        {
                            iclass: 'fa fa-lock set-permission',
                            ref: 'permissionrole',
                            title: 'Permisos',
                            class: 'mr-2'
                        }
                },
                {
                    default:
                        {
                            iclass: 'fas fa-trash',
                            title: 'Borrar'
                        }
                }
            ]
        }

        return {
            title,
            action,
            closeModal,
            table,
            reload,
            buttons,
            rol
        };
    }
}
</script>

<style scoped>

</style>
