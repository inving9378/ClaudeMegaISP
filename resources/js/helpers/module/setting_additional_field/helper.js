

export const initHelperFormField = (props, rows) => {
    $(document).on(
        "click",
        `#${props.idTable} .position-up`,
        function (e) {
            updatePositionUp(e, rows);
        }
    );

    $(document).on(
        "click",
        `#${props.idTable} .position-down`,
        function (e) {
            updatePositionDown(e, rows);
        }
    );
}


const updatePositionUp = (e, rows) => {
    const targetRowId = parseInt($(e.target).parent().attr("id-item"));
    const targetIndex = rows.value.findIndex(
        (obj) => obj.rowId == targetRowId
    );

    if (targetIndex > 0) {
        let previousRow = null;
        // Intercambia la posición con la fila anterior
        previousRow = rows.value[targetIndex - 1];
        rows.value[targetIndex - 1] = rows.value[targetIndex];
        rows.value[targetIndex] = previousRow;

        // Actualiza las posiciones de las filas
        rows.value.forEach((row, index) => {
            row.position = `<td><span>${index + 1}</span>
<button class="btn" id-item="${row.rowId}" data-position="${index + 1
                }"><i class="fas fa-arrow-up position-up"></i></button>
<button class="btn" id-item="${row.rowId}" data-position="${index + 1
                }"><i class="fas fa-arrow-down position-down"></i></button>
</td>`;
        });

        let actualRow = rows.value[targetIndex - 1];
        savePosition(actualRow, previousRow);
    }
};

const updatePositionDown = (e, rows) => {
    const targetRowId = parseInt($(e.target).parent().attr("id-item"));
    const targetIndex = rows.value.findIndex(
        (obj) => obj.rowId == targetRowId
    );

    if (targetIndex < rows.value.length - 1) {
        let nextRow = null;
        // Intercambia la posición con la fila siguiente
        nextRow = rows.value[targetIndex + 1];
        rows.value[targetIndex + 1] = rows.value[targetIndex];
        rows.value[targetIndex] = nextRow;

        // Actualiza las posiciones de las filas
        rows.value.forEach((row, index) => {
            row.position = `<td><span>${index + 1}</span>
<button class="btn" id-item="${row.rowId}" data-position="${index + 1
                }"><i class="fas fa-arrow-up position-up"></i></button>
<button class="btn" id-item="${row.rowId}" data-position="${index + 1
                }"><i class="fas fa-arrow-down position-down"></i></button>
</td>`;
        });

        let actualRow = rows.value[targetIndex + 1];
        savePosition(actualRow, nextRow);
    }
};

const savePosition = (val1, val2) => {
    let dataVal1 = {
        module_id: val1.module_id,
        name: val1.name,
        position: getDataPosition(val1.position),
    };

    let dataVal2 = {
        module_id: val2.module_id,
        name: val2.name,
        position: getDataPosition(val2.position),
    };

    let data = {
        data1: dataVal1,
        data2: dataVal2
    }

    axios
        .post(`/configuracion/additional-fields/update-position-field`, data)
        .then((response) => {

        })
        .catch((error) => {
            console.log(error);
        });


};

const getDataPosition = (position) => {
    const match = position.match(/data-position="(\d+)"/);
    return match ? match[1] : "";
};
