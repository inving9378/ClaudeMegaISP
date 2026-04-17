export const requestTicketData = async (id) => {
    let fields = {}
    await axios["post"](`/tickets/get-ticket-by-id/${id}`, {}).then((response) => {
        fields = response.data;
    });
    return fields;
}

export const requestParentTicketData = async (id) => {
    let fields = {}
    await axios["post"](`/tickets/get-parent-ticket-by-id/${id}`, {}).then((response) => {
        fields = response.data;
    });
    return fields;
}

export const requestChildTicketData = async (id) => {
    let fields = {}
    await axios["post"](`/tickets/get-child-ticket-by-id/${id}`, {}).then((response) => {
        fields = response.data;
    });
    return fields;
}

export const requestTicketThreadData = async (id) => {
    let fields = {}
    await axios["post"](`/tickets/get-ticket-thread-by-id/${id}`, {}).then((response) => {
        fields = response.data;
    });
    return fields;
}

export const requestEditedFieldsByIdInMessage = async (module, id) => {
    let fields = {}
    await axios["post"](`/fields-by-module/${id}`, {module}).then((response) => {
        fields = response.data;
    });
    return fields;
}

export const requestUserDataByTicket = async (id) => {
    let data = {}
    await axios["post"](`/tickets/get-user-data-by-ticket-id/${id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestChangeStatus = async (id,statusTicket) => {
    let data = {}
    await axios["post"](`/tickets/set-status-ticket-by-id/${id}`, {statusTicket}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getDataClient = async (id) => {
    let data = {}
    await axios["post"](`/tickets/get-data-client/${id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestStatisticsForTarjetsByStatus = async () => {
    let data = {}
    await axios["post"](`/tickets/request-statistics-for-tarjets-by-status`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestTicketAssignedToMe = async (value) => {
    let data = {}
    await axios["post"](`/tickets/request-ticket-assigned-to-me`, {value}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestTicketAssignedTo = async (value) => {
    let data = {}
    await axios["post"](`/tickets/request-ticket-assigned-to`, {value}).then((response) => {
        data = response.data;
    });
    return data;
}


export const getTicketsByDateAndStatus = async (startDate, endDate,status) => {
    let data = [];
    await axios["get"](`/tickets/get-tickets-new-by-date/${startDate}/${endDate}/${status}`).then((res) => {
        data = res.data;
    });
    return data;
}