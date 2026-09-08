<template>
    <Datatable
        module="administracion/document_template"
        model="DocumentTemplate"
        list="Listado de Plantillas"
        @table="table"
        :buttons="getButtonDatatable()"
        :editButton="{ modal: 'modalDocumentTemplates' }"
        :select_filter="filters"
    ></Datatable>

    <Template-Manager> </Template-Manager>
</template>

<script>
import Datatable from "../../../base/shared/Datatable";
import { onMounted, reactive, ref, onUnmounted, getCurrentInstance } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import TemplateManager from "./TemplateManager.vue";
import { action } from "./helper";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

export default {
    name: "DocumentTemplateListar",
    components: { Datatable, TemplateManager },
    props: { filters: String },
    setup(props) {
        const ns = `.leak983-documentTemplateListar-${getCurrentInstance().uid}`;
        onUnmounted(() => {
            $(document).off(ns);
        });
        const datatable = reactive({
            table: new DatatableHelper({}),
        });
        const reloadCrud = ref(true);
        const hasPermission = reactive({ data: new Permission({}) });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());

            $(document).on("click" + ns, "#addTemplateManager", function () {
                action.value = "/administracion/document_template/add";
                showModal();
            });
            $(document).on("click" + ns, ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                action.value = `/administracion/document_template/update/${idItem}`;
                showModal();
            });
            $(document).on(
                "click" + ns,
                `.show_document_template_pdf`,
                async function () {
                    let idItem = $(this).parent().attr("id-item");
                    let htmlData = $(this).parent().attr("data-html");
                    let data = {
                        html: htmlData,
                    };

                    const response = await axios.post(
                        `/administracion/document_template/show_content_template/${idItem}`,
                        data,
                        { responseType: "blob" } // Especifica que esperas un blob como respuesta
                    );

                    if (
                        response.headers["content-type"] === "application/json"
                    ) {
                        alert(response.data.message);
                    } else {
                        const blob = new Blob([response.data], {
                            type: "application/pdf",
                        });
                        const url = URL.createObjectURL(blob);
                        window.open(url, "_blank");
                    }
                }
            );
        });

        const closeModal = () => {};

        const showModal = () => {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDocumentTemplates')).show();
        };

        const reload = () => {
            datatable.table.reload();
        };

        const table = (refTable) => {
            datatable.table = new DatatableHelper(refTable);
        };

        const getButtonDatatable = () => {
            let buttons = {};
            buttons.contract = {
                class: "btn btn-outline-info waves-effect waves-light",
                iclass: "fa fa-plus",
                href: `javascript:void(0)`,
                id: "addTemplateManager",
            };
            if (hasPermission.data.canView("documentos.template.exportar_acuse")) {
                buttons.exportarAcuse = {
                    class: "btn btn-outline-secondary waves-effect waves-light",
                    iclass: "fa fa-file-pdf",
                    text: "Acuse de avance",
                    href: "/administracion/document_template/acuse/exportar",
                    target: "_blank",
                };
            }
            return buttons;
        };

        return {
            action,
            closeModal,
            table,
            reload,
            reloadCrud,
            getButtonDatatable,
        };
    },
};
</script>

<style scoped></style>
