import axios from "axios";
import Permission from "./Permission";

export const getValueDB = async (model, field) => {
    let data = {};
    await axios["post"](`/helper/get-value-colony-state-municipality`, {
        model,
        field,
        id: $("#module_id").val(),
    }).then((response) => {
        data = response.data;
    });
    return data;
};

export const getServicesByClientMainInformationId = async (idToFind) => {
    let data = {};
    await axios["post"](`/helper/get-services-by-client-main-information`, {
        idToFind: idToFind,
    }).then((response) => {
        data = response.data;
    });
    return data;
};

export const requestFieldsByModule = async (module) => {
    let fields = {};
    await axios["post"](`/fields-by-module`, { module: module }).then(
        (response) => {
            fields = response.data;
        }
    );
    return fields;
};

export const requestFieldsByModuleIdRelation = async (
    module,
    parent_module,
    id,
    relation
) => {
    let fields = {};
    await axios["post"](`/fields-by-module-and-relation`, {
        module,
        parent_module,
        id,
        relation,
    }).then((response) => {
        fields = response.data;
    });
    return fields;
};

export const requestEditedFieldsById = async (module, id) => {
    let fields = {};
    await axios["post"](`/fields-by-module/${id}`, { module }).then(
        (response) => {
            fields = response.data;
        }
    );
    return fields;
};

export const requestGeneralEditedFields = async (module) => {
    let fields = {};
    await axios["post"](`/fields-by-module/general/edited`, { module }).then(
        (response) => {
            fields = response.data;
        }
    );
    return fields;
};

export const requestFieldsByModuleWithModuleRequested = async (
    module,
    modelRequest,
    idModelRequest,
    idClient
) => {
    let fields = {};
    await axios["post"](`/fields-by-module-with-module-requested`, {
        module,
        modelRequest,
        idModelRequest,
        idClient,
    }).then((response) => {
        fields = response.data;
    });
    return fields;
};

export const requestColumnsDatatableByModule = async (module) => {
    let columns = {};
    await axios["post"](`/columns-by-module`, { module: module }).then(
        (response) => {
            columns = response.data;
        }
    );
    return columns;
};

export const requestColumnExpandDtByModule = async (module) => {
    let column = null;
    await axios["post"](`/column-expand-by-module`, { module: module }).then(
        (response) => {
            column = response.data.column;
        }
    );
    return column;
};

export const requestColumnsDatatableByModuleExceptColumns = async (
    module,
    columnsExcept
) => {
    let columns = {};
    await axios["post"](`/columns-by-module-except-columns`, {
        module: module,
        columnsExcept: columnsExcept,
    }).then((response) => {
        columns = response.data;
    });
    return columns;
};

export const requestAllColumnsDatatableByModule = async (module) => {
    let columns = {};
    await axios["post"](`/all-columns-by-module`, { module: module }).then(
        (response) => {
            columns = response.data;
        }
    );
    return columns;
};

export const requestRandomPassword = async () => {
    let password = {};
    await axios["post"](`/request-random-password`, {}).then((response) => {
        password = response.data;
    });
    return password;
};

export const requestGenerateUser = async (val) => {
    let user = {};
    await axios["post"](`/request-generate-user/${val}`, {
        location: window.location.href,
    }).then((response) => {
        user = response.data;
    });
    return user;
};

export const requestGenerateUserExist = async () => {
    let user = {};
    await axios["post"](`/request-generate-user-exist`, {
        location: window.location.href,
    }).then((response) => {
        user = response.data;
    });
    return user;
};

export const getUserAuthenticated = async () => {
    let data = {};
    await axios["post"](`/get-user-authenticated`, {}).then((response) => {
        data = response.data;
    });
    return data;
};

export const getDefaultValue = async (date) => {
    let data = {};
    await axios["post"](`/get-default-value/${date}`, {}).then((response) => {
        data = response.data;
    });
    return data;
};

export const requestMikrotikStatus = async (id) => {
    let status = {};
    await axios["post"](`/status-by-router/${id}`, {})
        .then((response) => {
            status = response.data;
        })
        .catch((error) => {
            status = {};
        });
    return status;
};

export const requestCreateMikrotikRules = async (id) => {
    let data = {};
    await axios["post"](`/create-rules-by-router/${id}`, {}).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const requestRemoveMikrotikRules = async (id) => {
    let data = {};
    await axios["post"](`/remove-rules-by-router/${id}`, {}).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const requestCloneClientToMikrotik = async (id) => {
    let data = {};
    await axios["post"](`/request-clone-client-to-mikrotik/${id}`, {}).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getData = async (id, module) => {
    let data = {};
    await axios["post"](`/get-data/${module}`, { id }).then((response) => {
        data = response.data;
    });
    return data;
};

export const hasPermissionToView = async (view) => {
    let hasPermission = { data: false };
    await axios["post"](`/has-permission-to-view/${view}`, {}).then(
        (response) => {
            hasPermission = response.data;
        }
    );
    return hasPermission.data;
};

export const allViewHasPermission = async () => {
    let hasPermission = [];
    await axios["post"](`/all-view-has-permission`, {}).then((response) => {
        hasPermission = _.mapKeys(response.data, (v) => v);
    });
    return hasPermission;
};

export const getAjaxDefaultValue = async (request) => {
    let data = null;
    await axios["post"](request, {}).then((response) => {
        data = response.data;
    });
    return data;
};

export const requestReminderPaymentAmount = async (id) => {
    let user = {};
    await axios["post"](
        `/cliente/billing/get-reminder-payment-amount/${id}`,
        {}
    ).then((response) => {
        user = response.data;
    });
    return user;
};
