import { ref } from "vue";


export const reloadEvents = ref(false);

export const payments = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/payment/get-totals/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const transactions = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/transaction/get-totals/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getCostAllServiceActive = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/payment/get-cost-all-service-active/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getCostAllService = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/payment/get-cost-all-service/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getPromotions = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/get-promotions/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getPeriodByAmount = async (client_id, amount) => {
    let data = {}
    await axios["post"](`/cliente/get-period-by-amount/${client_id}`, { amount }).then((response) => {
        data = response.data;
    });
    return data;
}

export const getIsClientPromisePayment = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/get-is-promise-payment/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getActiveServiceExpiration = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/payment/get-active-service-expiration/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getEventByDate = async (postData) => {
    let data = {}
    await axios["post"](`/fullcalendar/get-billing-configuration`, { postData }).then((response) => {
        data = response.data;
    });
    return data;
}

export const getTaskEvents = async (filters = null) => {
    let data = {}
    await axios["post"](`/fullcalendar/get-task-events`, filters).then((response) => {
        data = response.data;
    });
    reloadEvents.value = true;
    return data;
}

export const requestBillingInformationBlock = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/get-billing-information-block/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getClientDebit = async (client_id) => {
    let data = {}
    await axios["post"](`/cliente/debit/${client_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestPaymentMethod = async (payment_method_id) => {
    let data = {}
    await axios["post"](`/cliente/billing/get-payment-method/${payment_method_id}`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const getAvailablePeriodsByClient = async (clientId) => {
    const { data } = await axios.get(
        `/finanzas/invoices/get-available-periods-by-client/${clientId}`
    );
    return data;
};


