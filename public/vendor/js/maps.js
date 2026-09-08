function empty(value) {
    const nullables = ["", null, 0, 0.0, "0", undefined, "undefined", []];
    return nullables.includes(value);
}

/**
 * construct a DataTable with the id of an html table
 *
 * @param 	id 		String target tabole html id
 * @param 	option	Datatable option
 */
function create_table(
    id,
    data,
    columns,
    columnDefs,
    order = [[0, "asc"]],
    pagingLength = [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, "Todos"],
    ]
) {
    return $(id).DataTable({
        responsive: true,
        autoWidth: false,
        ajax: data,
        order: order,
        orderCellsTop: true,
        fixedHeader: true,
        columns: columns,
        searchBuilder: {
            conditions: {
                num: {
                    "!between": true,
                },
            },
        },
        columnDefs: columnDefs,
        processing: true,
        serverSide: true,
        lengthMenu: pagingLength,
        language: {
            lengthMenu: "Mostrar _MENU_ registros",
            zeroRecords: "No se encontró registros",
            info: "Mostrando del _START_ al _END_ de un total de _TOTAL_",
            infoEmpty: "No hay registros",
            emptyTable: "No hay datos para mostrar",
            loadingRecords: "Cargando...",
            processing: "Procesando...",
            search: "Buscar:",
            infoFiltered: "(filtrado de un total de _MAX_ registros)",
            decimal: ",",
            thousands: ".",
            paginate: {
                first: "Primera",
                last: "Última",
                next: "<i class='fas fa-angle-right'></i>",
                previous: "<i class='fas fa-angle-left'></i>",
            },
            searchPanes: {
                clearMessage: "Borrar todo",
                collapse: {
                    0: "Paneles de búsqueda",
                    _: "Paneles de búsqueda (%d)",
                },
                count: "{total}",
                countFiltered: "{shown} ({total})",
                emptyPanes: "Sin paneles de búsqueda",
                loadMessage: "Cargando paneles de búsqueda",
                title: "Filtros Activos - %d",
            },
            buttons: {
                copy: "Copiar",
                colvis: "Visibilidad",
                collection: "Colección",
                colvisRestore: "Restaurar visibilidad",
                copyKeys:
                    "Presione ctrl o u2318 + C para copiar los datos de la tabla al portapapeles del sistema. <br /> <br /> Para cancelar, haga clic en este mensaje o presione escape.",
                copySuccess: {
                    1: "Copiada 1 fila al portapapeles",
                    _: "Copiadas %d fila al portapapeles",
                },
                copyTitle: "Copiar al portapapeles",
                csv: "CSV",
                excel: "Excel",
                pageLength: {
                    "-1": "Mostrar todas las filas",
                    1: "Mostrar 1 fila",
                    _: "Mostrar %d filas",
                },
                pdf: "PDF",
                print: "Imprimir",
            },
        },
    });
}

function onDblclickTable(table, route, type) {
    table.on("click", "td", function (i, x) {
        const columnIndex = table.column(this).index();
        const columnName = table.context[0].aoColumns[columnIndex].data;

        const row = table.row(this).data();
        const existe = this.className.includes("dtr-control");

        if (!existe && columnName !== "actions") {
            const data = {
                id: row.id,
                type: type === "map_link" ? row.type : type,
            };

            simpleFetch(route, data, makeCreateTable());
        }
    });
}

function onDblclickCell(table, route) {
    table.on("click", "td", function () {
        let cadena = this.className;
        let existe = cadena.includes("dtr-control");

        if (!existe) {
            const data = {
                port_number: this.innerHTML,
                passive_equipment_id: document.getElementById(
                    "passive_equipment_id"
                ).value,
            };

            simpleFetch(route, data, makeCreateTable());
        }
    });
}

function backObject(route, id, type) {
    simpleFetch(route, { id: id, type: type }, makeCreateTable());
}

function makeCreateTable() {
    return async (response) => {
        const body = await response.json();
        const container = document.getElementById("data_object_content");
        container.innerHTML = body.view;
        createDataTable(body.type);
    };
}

async function setTable(
    id,
    paramsFun,
    nameColumns,
    title = "",
    rowLength = [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, "Todos"],
    ]
) {
    //obtengo los elementos HTML definidos como estandares
    const regularTable = document.getElementById(id);
    const cardBody = regularTable.parentNode;

    //obtengo el nombre que deberia tenerl esta tabla en la BD
    const dbTableName = regularTable.id.split("_table")[0];

    // Obtiene datos de una ruta específica ('mapas/table') utilizando la función simpleFetch y pasando el nombre de la tabla extraído como parámetro
    const response = await simpleFetch(
        "mapas/table",
        { name: dbTableName },
        async (response) => {
            return await response.json();
        }
    );

    // Busca un elemento FORM dentro del elemento cardBody y guarda una referencia a él en la variable form
    const form = searchTypeElementInElement(cardBody, "FORM");

    // Añadimos la columna de control que sera muy pequeña solo para contener le boton de + detalles
    nameColumns.splice(1, 0, "buttom");
    addControlColumn(regularTable);

    // Construye una tabla de objetos utilizando los datos recibidos y otros parámetros y la asigna a la variable dataTable
    const dataTable = buildObjectTable(
        regularTable.id,
        response.index_route,
        paramsFun,
        nameColumns,
        rowLength
    );

    // Asigna varios controladores de eventos a la tabla, utilizando diferentes funciones auxiliares
    onDblclickTable(dataTable, response.show_route, response.table.type);
    insertTitleToDataTable(
        empty(title) ? response.table.label : title,
        regularTable.id
    );
    insertMarginsToDatatable(regularTable.id);

    // Si hay un formulario en el elemento DIV obtenido anteriormente, agrega un botón de colapso a la tabla
    if (!empty(form)) {
        const collapse = searchTypeElementInElement(form, "DIV");
        insertButtonCollapseToDataTable(regularTable.id, collapse.id);

        // Agrega un controlador de eventos para el evento submit del formulario que ejecuta la función submitCollapseForm con varios parámetros
        form.addEventListener("submit", async function (e) {
            submitCollapseForm(
                e,
                response.store_route,
                collapse.id,
                regularTable.id,
                form
            );
        });
    }

    // Devuelve la tabla construida
    return dataTable;
}

function addControlColumn(table) {
    let th = document.createElement("th");
    th.textContent = "";
    let row = table.rows[0]; // selecciona la primera fila de la tabla
    row.insertCell(1).appendChild(th); // agrega el nuevo th en la segunda posición
}

function makeParams(extraData = "") {
    return (params) => {
        Object.assign(params, {
            id: document.getElementById("object_id").value,
            type: document.getElementById("object_type").value,
            extra: extraData,
        });
    };
}

function newObject(latitude, longitude) {
    let inputLatitude = document.getElementById("input_latitude");
    let inputLongitude = document.getElementById("input_longitude");

    inputLatitude.value = latitude;
    inputLongitude.value = longitude;

    modal.show();
}

function searchTypeElementInElement(element, type) {
    const nodeList = element.childNodes;
    for (let index in nodeList) {
        let item = nodeList[index];
        if (item.nodeName === type) return item;
    }
}

async function setSpecialTable(id, paramsFun, indexRoute, showRoute) {
    //obtengo los elementos HTML definidos como estandares
    const regularTable = document.getElementById(id);

    // Añadimos la columna de control que sera muy pequeña solo para contener le boton de + detalles
    let columns = [
        "fila",
        "1",
        "2",
        "3",
        "4",
        "5",
        "6",
        "7",
        "8",
        "9",
        "10",
        "11",
        "12",
    ];
    columns.splice(1, 0, "buttom");
    addControlColumn(regularTable);

    // Construye una tabla de objetos utilizando los datos recibidos y otros parámetros y la asigna a la variable dataTable
    const dataTable = buildObjectTable(id, indexRoute, paramsFun, columns);

    // Asigna varios controladores de eventos a la tabla, utilizando diferentes funciones auxiliares
    onDblclickCell(dataTable, showRoute, "port");
    insertTitleToDataTable("Puertos", id);
    insertMarginsToDatatable(id);

    // Devuelve la tabla construida
    return dataTable;
}

/*
|----------------------------------------------------------------------------
|  DATATABLE
|----------------------------------------------------------------------------
*/

function buildObjectTable(
    id,
    route,
    paramsFun,
    nameColumns,
    rowLength = [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, "Todos"],
    ]
) {
    // const originalTable = document.

    const data = {
        url: route,
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": window.CSRF_TOKEN,
        },
        data: (params) => {
            paramsFun(params);
        },
        dataSrc: function (json) {
            // Procesar los datos obtenidos del servidor
            json.data.map((item) => {
                item.buttom = null;
            });
            // Devolver los datos procesados
            return json.data;
        },
    };

    const columns = nameColumns.map((name) => {
        if (name === "buttom")
            return {
                data: name,
                orderable: false,
                searchable: false,
                width: "5px",
            };

        return { data: name, orderable: true, searchable: false };
    });

    const columnDefs = [
        {
            targets: [0],
            className: "d-none",
        },
        {
            targets: () => {
                const targets = [];
                const exceptions = [
                    "actions",
                    "fiber_number",
                    "buffer_number",
                    "first_buffer_id",
                    "first_fiber_number",
                    "second_buffer_id",
                    "second_fiber_number",
                ];

                for (let index = 1; index < nameColumns.length; index++) {
                    if (exceptions.includes(nameColumns[index])) continue;

                    targets.push(index);
                }
                return targets;
            },
            className: "text-center align-middle",
        },
    ];

    if (nameColumns.indexOf("actions") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("actions"),
            data: "actions",
            className: "text-center align-middle",
            render: function (data, type, row, meta) {
                return (
                    `<button type="button" ` +
                    `class="btn-outline-info border-0" ` +
                    `onclick="destroyObject('` +
                    data +
                    `', ` +
                    row["map_link_id"] +
                    `, '` +
                    id +
                    `', true)">` +
                    `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16">` +
                    `<path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5Zm-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5ZM4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06Zm6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528ZM8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5Z"/>` +
                    `</svg>` +
                    `</button>`
                );
            },
        });
    }

    if (nameColumns.indexOf("fiber_number") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("fiber_number"),
            data: "fiber_number",
            className: "align-middle",
            render: function (data, type, row, meta) {
                if (empty(data)) return null;

                return (
                    '<svg style="color:' +
                    row.fiber_color +
                    '; border: 1px solid #000; margin-right: 1em;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/></svg>' +
                    data
                );
            },
        });
    }

    if (nameColumns.indexOf("buffer_number") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("buffer_number"),
            data: "buffer_number",
            className: "align-middle",
            render: function (data, type, row, meta) {
                if (empty(data)) return null;

                return (
                    '<svg style="color:' +
                    row.buffer_color +
                    '; border: 1px solid #000; margin-right: 1em;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/></svg>' +
                    data
                );
            },
        });
    }

    if (nameColumns.indexOf("first_buffer_id") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("first_buffer_id"),
            data: "first_buffer_id",
            className: "align-middle",
            render: function (data, type, row, meta) {
                if (empty(data)) return null;

                return (
                    '<svg style="color:' +
                    row.first_buffer_color +
                    '; border: 1px solid #000; margin-right: 1em;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/></svg>' +
                    data
                );
            },
        });
    }

    if (nameColumns.indexOf("first_fiber_number") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("first_fiber_number"),
            data: "first_fiber_number",
            className: "align-middle",
            render: function (data, type, row, meta) {
                if (empty(data)) return null;

                return (
                    '<svg style="color:' +
                    row.first_fiber_color +
                    '; border: 1px solid #000; margin-right: 1em;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/></svg>' +
                    data
                );
            },
        });
    }

    if (nameColumns.indexOf("second_buffer_id") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("second_buffer_id"),
            data: "second_buffer_id",
            className: "align-middle",
            render: function (data, type, row, meta) {
                if (empty(data)) return null;

                return (
                    '<svg style="color:' +
                    row.second_buffer_color +
                    '; border: 1px solid #000; margin-right: 1em;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/></svg>' +
                    data
                );
            },
        });
    }

    if (nameColumns.indexOf("second_fiber_number") > 0) {
        columnDefs.push({
            targets: nameColumns.indexOf("second_fiber_number"),
            data: "second_fiber_number",
            className: "align-middle",
            render: function (data, type, row, meta) {
                if (empty(data)) return null;

                return (
                    '<svg style="color:' +
                    row.second_fiber_color +
                    '; border: 1px solid #000; margin-right: 1em;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/></svg>' +
                    data
                );
            },
        });
    }

    return create_table(
        "#" + id,
        data,
        columns,
        columnDefs,
        nameColumns.indexOf("fiber_number") > 0 ? [[0, "asc"]] : [[2, "asc"]],
        rowLength
    );
}

/*
|----------------------------------------------------------------------------
|  MODAL
|----------------------------------------------------------------------------
*/
function processResponseForModal() {
    return async (response) => {
        const body = await response.json();
        const container = document.getElementById("data_object_content");
        container.innerHTML = body.view;
        createDataTable(body.type);
        dataObjectModal.show();
    };
}

/*
|----------------------------------------------------------------------------
|  FETCH
|----------------------------------------------------------------------------
*/

async function submitForm(event, route, fun) {
    // Prevenir el envío del formulario por defecto
    event.preventDefault();

    // Convertir los datos del formulario en un objeto
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    simpleFetch(route, data, fun);
}

async function submitCollapseForm(event, route, collapseId, tableId, form) {
    // creamos la funcion que se ejetara si el fetch es exitoso
    const fun = async (response) => {
        // Analizar la respuesta como JSON
        const body = await response.json();

        // Ocultar el colapso, mostrar una notificación, actualizar la tabla y restablecer el formulario
        const collapse = new bootstrap.Collapse(
            document.getElementById(collapseId),
            { toggle: false }
        );
        collapse.hide();

        //proceso de notificación
        processNotification(body);

        //recargamos la tabla
        const table = $("#" + tableId).DataTable();
        table.ajax.reload();

        //resetamos el form
        form.reset();

        //recargamos mapa
        reChargeMarkers();
    };

    submitForm(event, route, fun);
}

async function submitSimpleForm(event, route, resetForm = true) {
    // creamos la funcion que se ejetara si el fetch es exitoso
    const fun = async (response) => {
        // Analizar la respuesta como JSON
        const body = await response.json();

        //proceso de notificación
        processNotification(body);

        if (resetForm) {
            //resetamos el form
            event.target.reset();
        }
    };

    submitForm(event, route, fun);
}

async function sendCustomForm(event, route, customFun) {
    // creamos la funcion que se ejetara si el fetch es exitoso
    const fun = async (response) => {
        // Analizar la respuesta como JSON
        const body = await response.json();

        //proceso de notificación
        processNotification(body);

        //resetamos el form
        event.target.reset();

        customFun(body);
    };

    submitForm(event, route, fun);
}

async function simpleFetch(route, data, fun = null) {
    // Se realiza la solicitud POST a la ruta especificada.
    try {
        const response = await fetch(route, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": window.CSRF_TOKEN,
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(data),
        });

        // Se verifica si la respuesta es exitosa. Si no lo es, se lanza una excepción con un mensaje de error obtenido de la respuesta JSON.
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message);
        }

        // Se verifica si la función no existe, de ser asi se pasa solo el mensaje recibido a la notificación, y de no ser asi se ejecuta esta funcion
        if (empty(fun)) {
            const body = await response.json();
            notification(body.message, true);
        } else {
            return await fun(response);
        }
    } catch (error) {
        // Se muestra un mensaje de error a través de la función "notification".
        notification(error.message, false);

        if (error.message === "CSRF token mismatch.") window.location.reload();
    }
}
/*
|----------------------------------------------------------------------------
|  notifications
|----------------------------------------------------------------------------
*/
function notification(message, isNotError) {
    let options = {
        animation: true,
        autohide: true,
        delay: 5000,
    };

    let myToastEl = document.getElementById("myToastEl");
    let myToastElDiv = document.getElementById("toastPlacement");
    let danger_icon = document.getElementById("danger_icon");
    let success_icon = document.getElementById("success_icon");
    let myToast = bootstrap.Toast.getOrCreateInstance(myToastEl, options);
    let divMessage = document.getElementById("message");

    myToastElDiv.hidden = false;

    if (isNotError) {
        success_icon.hidden = false;
        danger_icon.hidden = true;
        myToastEl.classList.remove("bg-danger");
        myToastEl.classList.add("bg-success");
    } else {
        success_icon.hidden = true;
        danger_icon.hidden = false;
        myToastEl.classList.remove("bg-success");
        myToastEl.classList.add("bg-danger");
    }
    if (message != undefined) {
        myToast.show();
        divMessage.innerHTML = message;
    }
}

function processNotification(contenido, myModal) {
    if (contenido.errors) {
        errors = contenido.errors;
        for (const property in errors) {
            if (property.includes(".")) {
                key = property.split(".")[0];
            } else {
                key = property;
            }

            let inputs = document.querySelectorAll("#" + key + " input");
            let divs = document.querySelectorAll("#" + key + " div");

            inputs.forEach((input) => {
                input.classList.add("is-invalid");
                input.hidden = false;
            });

            divs.forEach((div) => {
                div.innerHTML = errors[property];
                div.hidden = false;
            });
        }
        notification(
            "Ha occurrido un error, por favor revise la información.",
            false
        );
    } else {
        notification(contenido.message, contenido.res);
    }
}

function processSimpleNotification(fun = null) {
    return async (response) => {
        // Analizar la respuesta como JSON
        const body = await response.json();

        //si exite una funcion se ejecuta aca
        if (!empty(fun)) fun();

        //proceso de notificación
        processNotification(body);
    };
}
/*
|----------------------------------------------------------------------------
|  Polylines
|----------------------------------------------------------------------------
*/

const makePolylineButton = document.getElementById("make_polyline_button");
let poly;
let points = [];

makePolylineButton.addEventListener("click", async (event) => {
    const button = bootstrap.Button.getOrCreateInstance(makePolylineButton);
    button.toggle();
    if (button._element.classList.contains("active")) {
        points = [];

        poly = new google.maps.Polyline({
            strokeColor: "#000000",
            strokeOpacity: 1.0,
            strokeWeight: 3,
        });
        poly.setMap(map);
        // Add a listener for the click event
        map.addListener("click", addLatLng);

        markersArray.map((marker) => {
            google.maps.event.clearListeners(marker, "click");
            marker.addListener("click", addLatLng);
        });

        polylineArray.map((polyline) => {
            google.maps.event.clearListeners(polyline, "click");
            polyline.addListener("click", (event) => {
                addLatLng(event, polyline.mapLinkId);
            });
        });
    } else {
        let positions = poly.getPath().g.map((item) => {
            return {
                latitude: item.lat(),
                longitude: item.lng(),
            };
        });

        google.maps.event.clearListeners(map, "click");

        if (positions.length > 0) {
            const data = {
                map_proyect_id: document.getElementById("map_proyect_id").value,
                positions: positions,
            };

            simpleFetch(
                "mapas/map_route/create",
                data,
                processResponseForModal()
            );
        } else {
            reChargeMarkers();
        }
    }
});

function addLatLng(event, mapLinkId = null) {
    const path = poly.getPath();

    // Because path is an MVCArray, we can simply append a new coordinate
    // and it will automatically appear.
    path.push(event.latLng);
    points.push({
        position: event.latLng,
        map_link_id: mapLinkId,
    });

    // Add a new marker at the new plotted point on the polyline.
    const marker = new google.maps.Marker({
        position: event.latLng,
        title: "#" + path.getLength(),
        map: map,
    });

    markersArray.push(marker);
}

/*
|----------------------------------------------------------------------------
|  OBJECTS
|----------------------------------------------------------------------------
*/

async function destroyObject(
    route,
    objectId,
    isInModal = false,
    tableId = null
) {
    const statusConfirm = confirm(
        "Al borrar este objeto se borraran sus conexiones a otros equipos, ¿esta seguro de borrarlo?"
    );

    if (!statusConfirm) {
        return false;
    }

    // creamos la funcion que se ejetara si el fetch es exitoso
    const fun = async (response) => {
        // Analizar la respuesta como JSON
        const body = await response.json();

        if (!empty(tableId)) {
            //recargamos la tabla
            const table = $("#" + tableId).DataTable();
            table.ajax.reload();
        }

        //proceso de notificación
        processNotification(body);

        //se recargan los marcadores
        reChargeMarkers();

        //si esta en el modal lo cerramos
        if (isInModal) {
            dataObjectModal.hide();
        }
    };

    simpleFetch(route, { id: objectId }, fun);
}

/*
|----------------------------------------------------------------------------
|  INSERTS
|----------------------------------------------------------------------------
*/
function insertTitleToDataTable(title, tableId) {
    let length = document.getElementById(tableId + "_length");
    let children = length.childNodes;
    let label = document.createElement("label");
    label.className += " me-2";
    label.innerHTML = "<strong>" + title + "</strong>";
    length.insertBefore(label, children[0]);
}

function insertButtonCollapseToDataTable(tableId, collapseId) {
    let filter = document.getElementById(tableId + "_filter");

    let button = document.createElement("button");
    button.setAttribute("data-bs-toggle", "collapse");
    button.setAttribute("data-bs-target", "#" + collapseId);
    button.className += " btn btn-outline-primary btn-sm ms-2";
    button.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/></svg>';
    filter.appendChild(button);
}

function insertMarginsToDatatable(tableId) {
    $("#" + tableId + "_length").addClass("ms-1");
    $("#" + tableId + "_info").addClass("ms-1");
    $("#" + tableId + "_filter").addClass("me-1");
    $("#" + tableId + "_paginate").addClass("me-1");
}
/*
|----------------------------------------------------------------------------
|  Select2
|----------------------------------------------------------------------------
*/
function makeParamsForSelect2() {
    return (params) => {
        return {
            text: params.term || null,
            page: params.page || 1,
        };
    };
}

function makeParamsForSelect2WithObject() {
    return (params) => {
        const object = {
            id: document.getElementById("object_id").value,
            type: document.getElementById("object_type").value,
        };
        return {
            text: params.term || null,
            page: params.page || 1,
            object: object,
        };
    };
}

function makeParamsForSelect2ByManyParams(arrayParams) {
    return (params) => {
        arrayParams.map((item) => {
            const input = document.getElementById(item);
            params[item] = input.value;
        });
        Object.assign(params, {
            text: params.term || null,
            page: params.page || 1,
        });
        return params;
    };
}

function makeParamsForSelect2WithTale(idElement, typeElement, typeToSearch) {
    return (params) => {
        const objectId = document.getElementById(idElement).value;
        const objectType = document.getElementById(typeElement).value;
        const TypeToSearch = document.getElementById(typeToSearch).value;

        let object = {
            id: objectId,
            type: objectType,
        };

        return {
            type: TypeToSearch,
            text: params.term || null,
            object: object,
            page: params.page || 1,
        };
    };
}

function makeParamsForSelect2Static(
    idElement,
    objectType,
    typeToSearch = null
) {
    return (params) => {
        const objectId = document.getElementById(idElement).value;

        let object = {
            id: objectId,
            type: objectType,
        };

        return {
            type: typeToSearch,
            text: params.term || null,
            object: object,
            page: params.page || 1,
        };
    };
}

function makeParamsForSelect2ForPorts(idElement, typeElement, idPort) {
    return (params) => {
        const portId = document.getElementById(idPort).value;
        let objectId = document.getElementById(idElement).value;
        let objectType = document.getElementById(typeElement).value;

        if (objectType === "active_equipment") {
            const transceiver =
                document.getElementById("transceiver_search").value;

            if (!empty(transceiver)) {
                objectId = transceiver;
                objectType = "transceiver";
            } else {
                const card = document.getElementById("card_search").value;
                if (!empty(card)) {
                    objectId = card;
                    objectType = "card";
                }
            }
        }

        let object = {
            id: objectId,
            type: objectType,
        };

        return {
            id: portId,
            text: params.term || null,
            object: object,
            page: params.page || 1,
        };
    };
}

function setSelect2OnModal(id, modalId, route, fun) {
    return $("#" + id).select2({
        theme: "bootstrap-5",
        language: "es",
        dropdownParent: $("#" + modalId),
        ajax: {
            url: route,
            type: "POST",
            dataType: "json",
            delay: 250,
            headers: {
                "X-CSRF-TOKEN": window.CSRF_TOKEN,
            },
            data: (params) => fun(params),
            processResults: function (data, params) {
                params.page = data.current_page || 1;
                return {
                    results: data.data,
                    pagination: {
                        more: params.page * data.per_page < data.total,
                    },
                };
            },
            cache: false,
        },
    });
}

function setSelect2Simple(id, route) {
    $("#" + id).select2({
        theme: "bootstrap-5",
        language: "es",
        ajax: {
            url: route,
            type: "POST",
            dataType: "json",
            delay: 250,
            headers: {
                "X-CSRF-TOKEN": window.CSRF_TOKEN,
            },
            data: function (params) {
                return {
                    text: params.term || null,
                    page: params.page || 1,
                };
            },
            processResults: function (data, params) {
                params.page = data.current_page || 1;
                return {
                    results: data.data,
                    pagination: {
                        more: params.page * data.per_page < data.total,
                    },
                };
            },
            cache: false,
        },
    });
}

function setBrandSelect(route) {
    const data = [
        "box_type",
        "trench_type",
        "racket_type",
        "active_equipment_type",
        "passive_equipment_type",
    ];

    data.map((item) => {
        setSelect2SimpleByClass(route, item + "_brand_id", "data_object_modal");
    });
}

function setSelect2SimpleByClass(route, selectId, modalId) {
    $("#" + selectId).select2({
        theme: "bootstrap-5",
        language: "es",
        dropdownParent: $("#" + modalId),
        ajax: {
            url: route,
            type: "POST",
            dataType: "json",
            delay: 250,
            headers: {
                "X-CSRF-TOKEN": window.CSRF_TOKEN,
            },
            data: function (params) {
                return {
                    text: params.term || null,
                    page: params.page || 1,
                };
            },
            processResults: function (data, params) {
                params.page = data.current_page || 1;
                return {
                    results: data.data,
                    pagination: {
                        more: params.page * data.per_page < data.total,
                    },
                };
            },
            cache: false,
        },
    });
}

function setSelect2SimpleOnModal(id, route, modalId) {
    $("#" + id).select2({
        theme: "bootstrap-5",
        language: "es",
        allowClear: true,
        dropdownParent: $("#" + modalId),
        ajax: {
            url: route,
            type: "POST",
            dataType: "json",
            delay: 250,
            headers: {
                "X-CSRF-TOKEN": window.CSRF_TOKEN,
            },
            data: function (params) {
                return {
                    text: params.term || null,
                    page: params.page || 1,
                };
            },
            processResults: function (data, params) {
                params.page = data.current_page || 1;
                return {
                    results: data.data,
                    pagination: {
                        more: params.page * data.per_page < data.total,
                    },
                };
            },
            cache: false,
        },
    });
}

/*
|----------------------------------------------------------------------------
|  Generic
|----------------------------------------------------------------------------
*/
function disabledManySelect2(ids) {
    ids.forEach(function (id) {
        const select = $("#" + id);
        select.prop("disabled", true);
        select.val(null).trigger("change");
    });
}

function cleanSelect2(id) {
    const select = $("#" + id);
    select.prop("disabled", false);
    select.val(null).trigger("change");
}

function changeMapRoute(select) {
    const inputs = document.getElementsByClassName("map_route_element");

    inputs.forEach((input) => {
        if (!empty(select.value)) {
            input.required = false;
            input.value = null;
            input.disabled = true;
        } else {
            input.required = true;
            input.disabled = false;
        }
    });

    select.required = true;
    select.disabled = false;
}

function changeMapRouteElement() {
    const inputs = Array.from(
        document.getElementsByClassName("map_route_element")
    );
    const select = document.getElementById("continue_map_route_id");

    const isNotEmpty = inputs.map((input) => {
        input.required = true;
        input.disabled = false;
        return !empty(input.value);
    });

    if (isNotEmpty.indexOf(true) >= 0) {
        select.required = false;
        select.disabled = true;
        return null;
    }

    select.required = true;
    select.disabled = false;
}

function changeCardType(select) {
    const wanInputs = ["number_gibic_ports", "number_gibicC++_ports"];

    const lanInputs = [
        "number_SFP_ports",
        "number_SFP+_ports",
        "number_ethernet_ports",
    ];

    if (select.value === "WAN") {
        changeStatusInput(wanInputs, false);

        changeStatusInput(lanInputs, true);
    }

    if (select.value === "LAN") {
        changeStatusInput(wanInputs, true);

        changeStatusInput(lanInputs, false);
    }

    if (select.value === "") {
        changeStatusInput(wanInputs, false);

        changeStatusInput(lanInputs, false);
    }
}

function changeEquipmetType(select) {
    const equipmetInputs = [
        "equipment_search",
        "card_search",
        "transceiver_search",
        "equipment_port",
    ];

    if (select.value === "active_equipment") {
        changeStatusInput(["equipment_search"], true);
        changeStatusInput(["transceiver_search"], true);
        changeStatusInput(["card_search"], true);
        changeStatusInput(["equipment_port"], true);
    } else {
        changeStatusInput(["equipment_search"], true);
        changeStatusInput(["transceiver_search"], false);
        changeStatusInput(["card_search"], false);
        changeStatusInput(["equipment_port"], true);
    }

    if (select.value === "") {
        changeStatusInput(equipmetInputs, false);
    }
}

function changeStatusInput(inputs, enable = true) {
    inputs.map(function (inputId) {
        const input = document.getElementById(inputId);
        const parentNode = input.parentNode.parentNode;
        if (enable) {
            input.disabled = false;
            input.required = true;
            input.hidden = false;
            parentNode.hidden = false;
        } else {
            input.disabled = true;
            parentNode.hidden = true;
            input.value = null;
            input.required = false;
        }
    });
}

function changeConnectionType(select) {
    const equipmetInputs = [
        "output_id",
        "box_input_id",
        "buffer_id",
        "fiber_id",
        "tray_id",
        "fusion_port_id",
    ];

    if (select.value === "puerto") {
        changeStatusInput(["output_id"], true);
        changeStatusInput(["box_input_id"], false);
        changeStatusInput(["buffer_id"], false);
        changeStatusInput(["fiber_id"], false);
        changeStatusInput(["tray_id"], false);
        changeStatusInput(["fusion_port_id"], false);
    } else {
        changeStatusInput(["output_id"], false);
        changeStatusInput(["box_input_id"], true);
        changeStatusInput(["buffer_id"], true);
        changeStatusInput(["fiber_id"], true);
        changeStatusInput(["tray_id"], true);
        changeStatusInput(["fusion_port_id"], true);
    }

    if (select.value === "") {
        changeStatusInput(equipmetInputs, false);
    }
}

function changeFusionConnectionType(select) {
    const equipmetInputs = [
        "splitter_id",
        "splitter_port_id",
        "first_box_input_id",
        "first_buffer_id",
        "first_fiber_id",
        "second_box_input_id",
        "second_buffer_id",
        "second_fiber_id",
    ];

    const fiberInputs = [
        "first_box_input_id",
        "first_buffer_id",
        "first_fiber_id",
    ];

    const splitterInputs = ["splitter_id", "splitter_port_id"];

    const alwaysInputs = [
        "second_box_input_id",
        "second_buffer_id",
        "second_fiber_id",
    ];

    if (select.value === "") {
        changeStatusInput(equipmetInputs, false);
    } else if (select.value === "fibra") {
        changeStatusInput(splitterInputs, false);
        changeStatusInput(fiberInputs, true);
        changeStatusInput(alwaysInputs, true);
    } else {
        changeStatusInput(fiberInputs, false);
        changeStatusInput(splitterInputs, true);
        changeStatusInput(alwaysInputs, true);
    }
}

const check = document.getElementById("check_1");

let suma = 0;
if (check) {
    check.addEventListener("change", (event) => {
        if (event.target.checked) {
            ///aqui sumas
            suma++;
            return;
        }
        //restas
        suma--;
        return;
    });
}
