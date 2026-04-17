<template>
    <Datatable
        module="red/ipv4"
        model="Network"
        add="Agregar de red"
        @table="table"
        list="Listado de red"
        :buttonsInsideDatatable="buttons"
    ></Datatable>

    <div
        class="modal fade"
        id="crudred"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <NetworkCrud
                    :action="action"
                    @close-modal="closeModal"
                ></NetworkCrud>
            </div>
        </div>
    </div>

</template>

<script>
import Datatable from "../../base/shared/Datatable";
import NetworkCrud from "./NetworkCrud";
import {onMounted, reactive, ref} from "vue";
import DatatableHelper from "../../../helpers/datatableHelper";
export default {
    name: "NetworkListar",
    components: {Datatable, NetworkCrud},
    setup(props) {
        const title = ref('Crear red');
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const action = ref('/red/ipv4/add');

        const buttons = [];

        onMounted(() => {
            addButtons()
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });

            const showEditModal = (idItem, modal) => {
                $(`#${modal}`).modal('show');
                title.value = 'Editar red';
                action.value = `/red/ipv4/update/${idItem}`;
            };
        })

        const closeModal = (modal) => {
            if (modal == 'crudred'){
                $('#crudred').modal('hide');
            }
        };

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
                            ref: 'crudred',
                            title: 'Editar',
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
            table,
            reload,
            closeModal,
            buttons,

        };

    }
}

</script>

<style scoped>

</style>
