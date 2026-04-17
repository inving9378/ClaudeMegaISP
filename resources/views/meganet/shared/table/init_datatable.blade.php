<script src="{{ URL::asset('assets/libs/datatables/datatables.min.js')}}"></script>
<script src="{{ URL::asset('assets/libs/jszip/jszip.min.js')}}"></script>
<script src="{{ URL::asset('assets/libs/pdfmake/pdfmake.min.js')}}"></script>
<script src="{{ URL::asset('assets/js/pages/datatables.init.js')}}"></script>

<script>
    var table = null;
    var columns = _.map(JSON.parse('{!! json_encode($columns) !!}').data,(v,k) => {
        if (k != "action")
            return { "data": k};
        return {"data": "action", "bVisible": true, "bSortable": false, "asortable": false};
    });

    /* SECCION DATATABLE */
    table =  $('#table').DataTable({
        "responsive": false,
        "autoWidth": false,
        "scrollX": true,
        "processing": true,
        "serverSide": true,
        "pagination": true,
        "ajax": {
            "url": `/{{ strtolower($module) }}/table`,
            "type": "POST",
            "data": {_token: $('meta[name="csrf-token"]').attr('content')}
        },
        "columns": columns,
        "language": getLanguage(),
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: 'th:not(:last-child)'
                }
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: 'th:not(:last-child)'
                }
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: 'th:not(:last-child)'
                }
            },
            {
                extend: 'pdf',
                exportOptions: {
                    columns: 'th:not(:last-child)'
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: 'th:not(:last-child)'
                }
            }
        ]
    });

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
</script>
