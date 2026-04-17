import _, { isEmpty } from "lodash";
import { updateThisField } from "../hook/crudHook";
import MyUploadAdapter from "./UploadAdapter";
import Choices from "choices.js";
export const selectTransform = (val, orderByAlfabetic) => {
    let opts = [];
    if (_.size(val) > 0) {
        opts = _.map(val, (v, k) => {
            return { value: k, text: v };
        });

        try {
            if (orderByAlfabetic == true && !isEmpty(opts)) {
                opts = _.sortBy(opts, "text"); // Ordenar opciones por el campo 'text'
            }
        } catch (error) {
            console.log("1", error);
        }
    }
    return opts;
};

export const getOptions = async (
    val,
    idModel = null,
    orderByAlfabetic = false
) => {
    let opts = [];
    if (val)
        await axios
            .post("/get-options-select", { ...val, idModel: idModel })
            .then(
                (res) => (opts = selectTransform(res.data, orderByAlfabetic))
            );
    return opts;
};

export const getOptionsWithoutId = async (val, id) => {
    let opts = [];
    await axios
        .post(`/get-options-select/${id}`, { ...val })
        .then((res) => (opts = selectTransform(res.data)));
    return opts;
};

export const convertToBoostrapSelect = async (id, selected, allOptions) => {
    // Inicializa el multiselect
    await $(`#${id}`).multiselect({
        includeSelectAllOption: true,
        triggerOnChange: true,
        enableFiltering: true,
        filterPlaceholder: "Search...",
        select: function (values) {},
        deselect: function (values) {
            selected.value = $(`#${id}`).val() || [];
        },
        onDropdownHidden: function (event) {
            selected.value = $(`#${id}`).val() || [];
        },
        onSelectAll: function () {
            selected.value = _.map(allOptions, (opt) => opt.value);
        },
        onDeselectAll: function () {
            selected.value = [];
        },
    });

    $(`#${id}`).val(selected.value);
    $(`#${id}`).multiselect("refresh");

    // Adaptación a Bootstrap 5
    $(`#${id}`)
        .parent()
        .find("div.btn-group > button")
        .removeAttr("data-toggle");
    $(`#${id}`)
        .parent()
        .find("div.btn-group > button")
        .attr("data-bs-toggle", "dropdown");
};

export const convertToBoostrapSelectTeam = async (id, selected, allOptions) => {
    if (id) {
        $(`#${id}`).multiselect({
            includeSelectAllOption: true,
            enableFiltering: true,
            filterPlaceholder: "Search...",
            nonSelectedText: "Select options",
            nSelectedText: "selected",
            allSelectedText: "All selected",
            selectAllText: "Select all",
            triggerOnChange: true,
            onChange: function (option, checked) {
                const value = $(option).val();
                const isTeam = allOptions.hasOwnProperty(value);

                if (isTeam) {
                    const users = Object.keys(allOptions[value]); // Obtener los IDs de los usuarios del equipo
                    const currentSelected = selected.value || [];

                    if (checked) {
                        // Si se selecciona un equipo, agregar todos sus usuarios a 'selected.value'
                        selected.value = [
                            ...new Set([...currentSelected, ...users]),
                        ];

                        // Seleccionar visualmente los usuarios del equipo
                        const currentVisualSelected = $(`#${id}`).val() || [];
                        const newSelection = [
                            ...new Set([...currentVisualSelected, ...users]),
                        ];
                        $(`#${id}`).multiselect("select", newSelection);
                    } else {
                        // Si se deselecciona un equipo, eliminar todos sus usuarios de 'selected.value'
                        selected.value = currentSelected.filter(
                            (user) => !users.includes(user)
                        );
                    }
                } else {
                    // Si es un usuario individual, añadirlo o eliminarlo según esté seleccionado
                    if (checked) {
                        selected.value = [
                            ...new Set([...selected.value, value]),
                        ];
                    } else {
                        //eliminar usuario
                        selected.value = selected.value.filter(
                            (user) => user != value
                        );
                        console.log(selected.value);
                    }
                }

                // Actualizar la vista del multiselect
                $(`#${id}`).multiselect("refresh");
            },
            onSelectAll: function () {
                // Seleccionar todos los usuarios de todos los equipos
                selected.value = Object.keys(allOptions).reduce((acc, team) => {
                    return [...acc, ...Object.keys(allOptions[team])];
                }, []);

                // Seleccionar visualmente todos los usuarios y equipos
                const allUsers = selected.value;
                const allTeams = Object.keys(allOptions);
                $(`#${id}`).multiselect("select", [...allUsers, ...allTeams]);
            },
            onDeselectAll: function () {
                selected.value = [];
                $(`#${id}`).multiselect("deselectAll", false);
            },
            onDropdownHidden: function () {
                selected.value = $(`#${id}`).val() || [];
            },
        });

        // Inicializar el valor seleccionado
        $(`#${id}`).val(selected.value);
        $(`#${id}`).multiselect("refresh");

        // Adaptación a Bootstrap 5
        $(`#${id}`)
            .parent()
            .find("div.btn-group > button")
            .removeAttr("data-toggle");
        $(`#${id}`)
            .parent()
            .find("div.btn-group > button")
            .attr("data-bs-toggle", "dropdown");
    }
};

const waitForElement = async (selector, timeout = 5000) => {
    return new Promise((resolve, reject) => {
        const startTime = Date.now();
        const interval = setInterval(() => {
            const element = document.querySelector(selector);
            if (element) {
                clearInterval(interval);
                resolve(element);
            } else if (Date.now() - startTime > timeout) {
                clearInterval(interval);
                reject(
                    new Error(
                        `Elemento ${selector} no encontrado dentro del tiempo especificado`
                    )
                );
            }
        }, 100);
    });
};

export const convertToSelect2 = async (
    id,
    allOptions,
    val,
    placeholder,
    orderByAlfabetic = false
) => {
    try {
        const element = `select#${id}`;

        let options = _.map(allOptions.value, (opt) => {
            if (opt.value == val)
                return { value: opt.value, label: opt.text, selected: true };
            return { value: opt.value, label: opt.text };
        });

        try {
            if (orderByAlfabetic && !isEmpty(options)) {
                options = _.sortBy(options, "label");
            }
        } catch (error) {
            console.log("2", error);
        }

        let defaultOpt = placeholder
            ? [{ value: "null", label: placeholder }]
            : [];

        let choice = new Choices(element, {
            shouldSort: false,
            choices: defaultOpt,
        });
        choice.setChoices(options, "value", "label", false);
        return choice;
    } catch (error) {
        console.error(error.message);
        return null;
    }
};

export const convertToSelect2WithSearch = async (
    id,
    allOptions,
    val,
    placeholder,
    orderByAlfabetic = false
) => {
    // Generate the options list and set the selected one if it matches `val`
    let options = _.map(allOptions._rawValue, (opt) => {
        if (opt.value == val) {
            return { value: opt.value, label: opt.text, selected: true }; // Mark the matching value as selected
        }
        return { value: opt.value, label: opt.text };
    });

    try {
        if (orderByAlfabetic && !isEmpty(options)) {
            options = _.sortBy(options, "label");
        }
    } catch (error) {
        console.log("3", error);
    }
    // Initialize Choices without options initially
    let choice = new Choices(`select#${id}`, {
        shouldSort: false,
        renderChoiceLimit: 5, // Limit displayed elements to 5
        searchFloor: 2, // Start search after 2 characters
        choices: [], // No initial choices
        placeholderValue: placeholder || "", // Placeholder text
        noChoicesText: "Escriba al menos 2 caracteres",
    });

    // Set initial choices, including the selected one
    choice.setChoices(options, "value", "label", true);

    const selectElement = document.querySelector(`select#${id}`);
    const selectContainer = selectElement.parentNode;
    selectContainer.style.position = "relative";

    // Create the clear button to reset selections
    const clearButton = document.createElement("button");
    clearButton.textContent = "x";
    clearButton.type = "button";
    clearButton.style.cssText = `
        background: transparent;
        color: black;
        border: none;
        cursor: pointer;
        border-radius: 50%;
        font-size: 14px;
        position: absolute;
        top: 50%;
        right: 50px;
        transform: translateY(-50%);
        padding: 0;
    `;

    // Insert the clear button inside the select container
    selectContainer.appendChild(clearButton);

    // Event to clear options and reset the value to null
    clearButton.addEventListener("click", function () {
        choice.clearChoices(); // Clear all options
        choice.clearStore(); // Clear the select value
        selectElement.value = null; // Set the select value to null
        updateThisField({ field: id, value: null });
    });

    // Filter options based on the search input
    choice.passedElement.element.addEventListener("search", function (event) {
        const searchTerm = event.detail.value;
        // Show options only if the search term has at least 2 characters
        if (searchTerm.length >= 2) {
            const filteredOptions = options.filter((opt) =>
                opt.label.toLowerCase().includes(searchTerm.toLowerCase())
            );
            choice.setChoices(filteredOptions, "value", "label", true);
        } else {
            // Clear visible options if the search term is too short
            choice.clearChoices();
        }
    });

    return choice;
};
function MyCustomUploadAdapterPlugin(editor) {
    editor.plugins.get("FileRepository").createUploadAdapter = (loader) => {
        return new MyUploadAdapter(loader);
    };
}

// TODO ver para hacer el date range picker
export const convertToCkeditor = async (id, editor) => {
    return await ClassicEditor.create(document.querySelector(`#${id}`), {
        extraPlugins: [MyCustomUploadAdapterPlugin],
    })
        .then((e) => {
            e.model.document.on("change:data", (v) => {
                editor.value = e.getData();
            });

            const button = document.createElement("button");
            button.type = "button";
            button.classList.add("ck-button.cursor-pointer");
            button.style = "opacity: 0.7";
            button.innerHTML =
                '<span class="ck-button__label"><?xml version="1.0" encoding="utf-8"?><svg width="25px" height="25px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 8L3 11.6923L7 16M17 8L21 11.6923L17 16M14 4L10 20" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';

            // Manejar el clic
            button.addEventListener("click", () => {
                let content = document.querySelector(".ck-editor__main");
                let code = document.getElementById(`html-${id}`);
                if (content.classList.contains("hidden")) {
                    content.classList.remove("hidden");
                    e.setData(code.value);
                    code.classList.add("hidden");
                } else {
                    content.classList.add("hidden");
                    code.classList.remove("hidden");
                }
            });

            document.querySelector(".ck-toolbar__items").appendChild(button);
            return e;
        })
        .catch((error) => {
            console.error(error);
        });
};

export const convertToDateRangePicker = async (id) => {
    return await $(`#${id}-range`).daterangepicker();
};

export const arrayOfObjectToArrayOfArray = (array) => {
    return array.map(function (obj) {
        return Object.keys(obj)
            .sort()
            .map(function (key) {
                return obj[key];
            });
    });
};

export const getLocation = (geodata) => {
    if (geodata) {
        let location = _.split(geodata, ",");
        if (location.length == 2) {
            return {
                lat: _.toNumber(location[0]),
                lng: _.toNumber(location[1]),
            };
        }
    } else {
        return { lat: 23.1188897, lng: -82.3925687 };
    }
};

const orderByAlfabetic = () => {};
