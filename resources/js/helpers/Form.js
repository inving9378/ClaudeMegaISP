import Errors from "./Errors";
import _ from "lodash";

class Form {
    /**
     * Create a new Form instance.
     *
     * @param {object} data
     */
    constructor(data) {
        this.originalData = data
            ? _.mapValues(data, (v) => (v && v.value) || null)
            : {};
        this.originalJson = data;

        let values = _.cloneDeep(this.originalData);
        for (let field in values) {
            this[field] = values[field];
        }

        this.errors = new Errors();
        this.responseForbidden = null;
    }

     /**
     * Helper para validar si un valor es realmente "activo"
     */
    _isCheckboxChecked(value) {
        // Consideramos marcado si es: true, 1, o el string "1"
        return value === true || value === 1 || value === "1";
    }

    /**
     * Fetch all relevant data for the form.
     */
    data() {
        let data = {};
        for (let property in this.originalData) {
            // 1. Obtener el tipo de campo
            let fieldConfig = this.originalJson[property];
            let type = typeof fieldConfig === 'object' ? fieldConfig.type : '';

            // 2. Definir los tipos que son checkboxes
            const checkboxTypes = [
                "input-checkbox-with-inputs",
                "input-checkbox",
                "input-checkbox-after-withou-validation-error",
            ];

            if (checkboxTypes.includes(type)) {
                // Si es un checkbox, forzamos a 1 o 0 (entero)
                data[property] = this._isCheckboxChecked(this[property]) ? 1 : 0;
            } else {
                // 3. Lógica para los demás campos
                data[property] = this[property] !== null && this[property] !== 'null'
                    ? this[property]
                    : null;
            }
        }
        return data;
    }

    /**
     * Reset the form fields.
     */
    reset() {
        for (let field in this.originalData) {
            this[field] = null;
        }

        this.errors.clear();
    }

    /**
     * Send a POST request to the given URL.
     * .
     * @param {string} url
     */
    post(url) {
        return this.submit("post", url);
    }

    /**
     * Send a PUT request to the given URL.
     * .
     * @param {string} url
     */
    put(url) {
        return this.submit("put", url);
    }

    /**
     * Send a PATCH request to the given URL.
     * .
     * @param {string} url
     */
    patch(url) {
        return this.submit("patch", url);
    }

    /**
     * Send a DELETE request to the given URL.
     * .
     * @param {string} url
     */
    delete(url) {
        return this.submit("delete", url);
    }

    /**
     * Submit the form.
     *
     * @param {string} requestType
     * @param {string} url
     */
    submit(requestType, url, type = "reset") {
        return new Promise((resolve, reject) => {
            axios[requestType](url, this.data())
                .then((response) => {
                    this.onSuccess(response.data, type);

                    resolve(response.data);
                })
                .catch((error) => {
                    if (error.response.status === 422) {
                        this.onFail(error.response.data.errors);
                        reject(error.response.data);
                    } else if (error.response.status === 403) {
                        this.actionForbidden(error.response.data);
                    } else {
                        reject(error.response.data);
                    }
                });
        });
    }

    uploadFile(url, type = "reset") {
        let data = new FormData();
        // 1. Definir qué tipos se comportan como checkbox
        const checkboxTypes = [
            "input-checkbox",
            "input-checkbox-with-inputs",
            "input-checkbox-after-withou-validation-error"
        ];

        for (let property in this.originalData) {
            const fieldConfig = this.originalJson[property];
            const fieldType = typeof fieldConfig === 'object' ? fieldConfig.type : '';

            // 2. Si es checkbox, enviar 1 o 0
            if (checkboxTypes.includes(fieldType)) {
                data.append(property, this._isCheckboxChecked(this[property]) ? 1 : 0);
                continue; // Pasar al siguiente campo
            }
            // Procesamiento normal para otros campos
            if (this[property] != null && (property != 'files' && property != 'attachments')) {
                data.append(property, this[property]);
            }

            if ((property == 'files' || property == 'attachments') && this[property] != null) {
                for (let i = 0; i < this[property].length; i++) {
                    let file = this[property][i];
                    data.append(`${property}[${i}]`, file);
                }
            }
        }

        return new Promise((resolve, reject) => {
            axios["post"](url, data, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
            })
                .then((response) => {
                    this.onSuccess(response.data, type);

                    resolve(response.data);
                })
                .catch((error) => {
                    if (error.response.status === 422) {
                        this.onFail(error.response.data.errors);
                        reject(error.response.data);
                    } else if (error.response.status === 403) {
                        this.actionForbidden(error.response.data);
                    } else {
                        reject(error.response.data);
                    }
                });
        });
    }

    /**
     * Handle a successful form submission.
     *
     * @param {object} data
     */
    onSuccess(data, type) {
        if (type == "reset") this.reset();
    }

    /**
     * Handle a failed form submission.
     *
     * @param {object} errors
     */
    onFail(errors) {
        this.errors.record(errors);
    }

    actionForbidden(data) {
        this.responseForbidden = data;
    }

    showResponseForbidden() {
        return this.responseForbidden;
    }
}

export default Form;
