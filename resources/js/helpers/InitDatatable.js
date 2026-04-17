export const initDatatable = (col, module, id, editButton, buttonsInsideDatatable, idModule, filters, allHeaders, hasActionColumn, showInHeader) => {
    let taked = _.take(col, showInHeader);
    let columns = _.map(taked, (e) => {
        return {"data": e};
    });

    if (hasActionColumn) {
        columns.push({"data": "action", "bVisible": true, "bSortable": false, "asortable": false});
    }

    if (hasColumnDtControl(columns)) {
        columns.unshift({
            className: 'dt-control',
            orderable: false,
            data: null,
            defaultContent: '',
        })
    } else {
        columns.unshift({
            className: '',
            orderable: false,
            data: null,
            defaultContent: '',
        })
    }

    /* SECCION DATATABLE */
    let table = $(`#${id}`).DataTable({
        "responsive": true,
        "autoWidth": false,
        "scrollX": true,
        "processing": true,
        "serverSide": true,
        "pagination": true,
        "ajax": {
            "url": `/${module}/table`,
            "type": "POST",
            data: function (data) {
                data._token = $('meta[name="csrf-token"]').attr('content');

                // add editButton custom
                _.forEach(editButton, (v, k) => {
                    if (v) data[k] = v;
                });

                data.buttons = JSON.stringify(buttonsInsideDatatable.value);
                data.idModule = idModule;

                data.filters = JSON.stringify(filters);
            }
        },
        "columns": columns,
        "language": getLanguage(),
        'pageLength': 100,
        'lengthMenu': [[10, 100, 500, 1000], [10, 100, 500, 1000]],
    });

     $(`#${id} tbody`).on('click', 'td.dt-control', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);

        if (row.child.isShown()) {
             // This row is already open - close it
            row.child.hide();
             tr.removeClass('shown');
         } else {

             row.child(format(row.data(), allHeaders, taked)).show();
             tr.addClass('shown');
         }
    });

    return table;
};


function getLanguage() {
    return {
        "decimal": "",
        "emptyTable": "No hay datos disponibles en la tabla",
        "info": "Mostrando desde _START_ hasta _END_ de _TOTAL_ entradas",
        "infoEmpty": "Mostrando desde 0 hasta 0 de 0 entradas",
        "infoFiltered": "(filtrado de _MAX_ entradas)",
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": "Mostrar _MENU_ entradas",
        "loadingRecords": "Cargando...",
        "processing": "Procesando...",
        "search": "Buscar:",
        "zeroRecords": "No existen entradas que coincidan con sus parámetros de búsqueda",
        "paginate": {
            "first": "Primera",
            "last": "Última",
            "next": "Siguiente",
            "previous": "Anterior"
        },
        "aria": {
            "sortAscending": ": activar para ordenar la columna ascendentemente",
            "sortDescending": ":activar para ordenar la columna descendentemente"
        }
    }
}

function format(d, allHeaders, res) {
    let html = `<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">`;

    let moreHeader = _.filter(allHeaders.value, (v) => {
        return !res.includes(v.name) && v.name != "action";
    });


    _.forEach(moreHeader, v => {
        if (v.user_column_datatable_module.length === 0) {
            html = `${html} <tr>
<td>${v.label}</td><td>${d[v.name]}</td>
</tr>`;
        }
    })

    html = `${html}</table>`;
    return html;
}

function hasColumnDtControl(columns)
{
    return columns.length > 8
}
