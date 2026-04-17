import axios from "axios";
import { ref } from "vue";
import moment from "moment";
export const currentYearMonthlyBonus = ref(null);
export const errorMessage = ref(null);
export const dateSearchData = ref();
export const date = ref("");
export const from = ref(null);
export const to = ref(null);
export const filtersCustomers = ref([]);

export const diableDatesFromWeek = (date) => {
    const dayOfWeek = date.getDay();
    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const isFutureDate = date > today;
    return !isWeekend || isFutureDate;
};

export const getListPaymentsOfCustomers = async (
    id,
    page,
    rowsPerPage,
    search,
    start,
    dir,
    order,
    filtersCustomers
) => {
    let result = { data: [], total: 0 };
    await axios
        .get(`/vendedores/payments/${id}/getListPayments`, {
            params: {
                page,
                rowsPerPage,
                search,
                start,
                dir,
                order,
                filtersCustomers,
            },
        })
        .then((response) => {
            result = {
                data: response.data.client_payments,
                total: response.data.total,
            };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};

export const getAllPaymentsOfCustomers = async (id) => {
    let data = [];
    await axios["get"](`/vendedores/payments/${id}/getPayments`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getDataSeller = async (id) => {
    let data = [];
    await axios["get"](`/vendedores/payments/${id}/getDataSeller`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            console.log(e);
        });
    return data;
};

export const getRuleDataSeller = async (
    id,
    from = null,
    to = null,
    general = false
) => {
    let data = [];
    await axios["post"](`/vendedores/payments/${id}/getRuleDataSeller`, {
        from,
        to,
        general,
    })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getPeriodsFromSeller = async (id) => {
    let data = [];
    await axios["post"](`/vendedores/payments/get-periods-from-seller/${id}`, {
        from,
        to,
    })
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getMontlyCommissionsBySeller = async (id, year = null, type) => {
    let data = [];
    await axios["post"](
        `/vendedores/payments/${id}/getMontlyCommissionsBySeller`,
        {
            year,
            type,
        }
    )
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getPaymentsBySeller = async (user, data) => {
    let result = null;
    await axios
        .post(`/vendedores/payments-sellers/payments-by-seller/${user}`, data)
        .then((response) => {
            let { current_page, from, to, total, data } = response.data;
            result = { current_page, from, to, total, data };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};

export const getPendingPaymentsBySeller = async (user, period) => {
    let result = null;
    await axios
        .post(
            `/vendedores/payments-sellers/pending-payments-by-seller/${user}`,
            { period }
        )
        .then((response) => {
            result = response;
        })
        .catch((error) => {
            result = null;
        });
    return result;
};

export const getDiscountsBySeller = async (seller, data) => {
    let result = null;
    await axios
        .post(
            `/vendedores/payments-sellers/discounts-by-seller/${seller}`,
            data
        )
        .then((response) => {
            let { current_page, from, to, total, data } = response.data;
            result = { current_page, from, to, total, data };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};

export const getPendingDiscountsBySeller = async (user) => {
    let result = [];
    await axios
        .get(`/vendedores/payments-sellers/pending-discounts/${user}`)
        .then((response) => {
            result = response.data;
        })
        .catch((error) => {
            result = [];
        });
    return result;
};

export const getCurrentBalanceAccount = async (id) => {
    let data = 0;
    await axios["post"](
        `/vendedores/payments-sellers/current-balance-account/${id}`
    )
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {});
    return data;
};

export const getStatementAccount = async (id, statement = null) => {
    let data = null;
    await axios["post"](
        `/vendedores/payments-sellers/${
            statement ? statement : "statement"
        }-account/${id}`
    )
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {
            data = null;
        });
    return data;
};

export const getIncomesAccount = async (id) => {
    let data = {
        current_balance: 0,
        income: 0,
        expenses: 0,
    };
    await axios["post"](`/vendedores/payments-sellers/statement-account/${id}`)
        .then((response) => {
            data = response.data;
        })
        .catch((e) => {});
    return data;
};

export const years = () => {
    let year = moment().year();
    let years = [];
    for (let index = 2024; index <= year; index++) {
        years.push({
            label: index,
            value: index,
        });
    }
    return years;
};

export const fieldsRules = {
    fixed_salary: {
        label: "Sueldo",
        name: "fixed_salary_format",
    },
    sales_commission: {
        label: "Comisión por venta",
        name: "sales_commission_format",
    },
    additional_sales_commissions: {
        label: "Comisión por venta adicional",
        name: "additional_sales_commissions_format",
    },
    installation_cost: {
        label: "Costo de instalacion",
        name: "installation_cost",
    },
    distributors_commission: {
        label: "Comisión Distribuidores",
        name: "distributors_commission_format",
    },
};

export const months = [
    {
        name: "January",
        label: "Enero",
    },
    {
        name: "February",
        label: "Febrero",
    },
    {
        name: "March",
        label: "Marzo",
    },
    {
        name: "April",
        label: "Abril",
    },
    {
        name: "May",
        label: "Mayo",
    },
    {
        name: "June",
        label: "Junio",
    },
    {
        name: "July",
        label: "Julio",
    },
    {
        name: "August",
        label: "Agosto",
    },
    {
        name: "September",
        label: "Septiembre",
    },
    {
        name: "October",
        label: "Octubre",
    },
    {
        name: "November",
        label: "Noviembre",
    },
    {
        name: "December",
        label: "Diciembre",
    },
];

export const monthsLang = {
    January: "Enero",
    February: "Febrero",
    March: "Marzo",
    April: "Abril",
    May: "Mayo",
    June: "Junio",
    July: "Julio",
    August: "Agosto",
    September: "Septiembre",
    October: "Octubre",
    November: "Noviembre",
    December: "Diciembre",
};

export const getTotalAmountCommission = async (id) => {
    let data = [];
    await axios["get"](
        `/configuracion/comisiones/${id}/get-total-amount-commission`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const getCommisionsBySeller = async (id) => {
    let data = [];
    await axios["get"](
        `/configuracion/comisiones/${id}/get-commissions-by-seller`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const getRuleBySeller = async (seller_id) => {
    let data = [];
    await axios["get"](
        `/configuracion/reglas-comisiones/get-rule-by-seller/${seller_id}`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const getDetailsOfCommission = async (id) => {
    let data = [];
    await axios["get"](
        `/configuracion/comisiones/${id}/get-details-commission`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const getMethodsPayments = async () => {
    let data = [];
    await axios["get"](`/configuracion/metodos-de-pago/get-all-methods`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getBranchs = async () => {
    let data = [];
    await axios["get"](`/administracion/sucursal/all`).then((response) => {
        data = response.data;
    });
    return data;
};

export const getTechnicals = async () => {
    let result = null;
    await axios
        .get(`/sellers/cuts/technicals`)
        .then((res) => {
            result = res.data;
        })
        .catch((e) => {
            result = null;
        });
    return result;
};

export const getAllUsers = async () => {
    let data = [];
    await axios["get"](`/administracion/user/get-all-users`).then(
        (response) => {
            data = response.data.data;
        }
    );
    return data;
};

export const GetCountCommissionsPending = async (id) => {
    let response = {};
    await axios["get"](
        `/configuracion/comisiones/${id}/get-commissions-pending`
    ).then((res) => {
        response = res.data.commissions;
    });
    return response;
};

export const getCommissionsNotPaidBySeller = async (id) => {
    let data = [];
    await axios["get"](
        `/configuracion/comisiones/get-list-commissions-by-seller/${id}`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

// PAYMENTS
export const getAllPayments = async (id, page, rowsPerPage, search) => {
    let result = { data: [], total: 0 };
    await axios
        .get(`/vendedores/payments-sellers/${id}/get-all-payments-of-seller`, {
            params: {
                page,
                rowsPerPage,
                search,
            },
        })
        .then((response) => {
            result = {
                data: response.data.data,
                total: response.data.total,
            };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};

export const getPaymentById = async (id) => {
    let data = [];
    await axios["get"](`/vendedores/payments-sellers/${id}/edit`).then(
        (response) => {
            data = response.data;
        }
    );
    return data;
};

export const getPaymentsOfSeller = async (id, startDate, endDate, status) => {
    let data = [];
    await axios["get"](
        `/vendedores/payments-sellers/${id}/${startDate}/${endDate}/${status}/get-all-payments-of-seller`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const createPayment = async (data) => {
    let response = null;
    await axios["post"](`/vendedores/payments-sellers/store`, data)
        .then((res) => {
            response = res.data;
        })
        .catch((e) => {
            response = null;
        });
    return response;
};

export const collectDebt = async (data) => {
    let response = null;
    await axios["post"](`/vendedores/payments-sellers/collect-debt`, data)
        .then((res) => {
            response = res.data;
        })
        .catch((e) => {
            response = null;
        });
    return response;
};

export const showPaymentDetails = async (data) => {
    let response = {};
    await axios["post"](`/vendedores/payments-sellers/details`, data).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

export const showDetailsFromPaymentType = async (data) => {
    let response = {};
    await axios["post"](
        `/vendedores/payments-sellers/details-from-payment-type`,
        data
    ).then((res) => {
        response = res.data;
    });
    return response;
};

export const showDetailsFromDiscountType = async (data) => {
    let response = {};
    await axios["post"](
        `/vendedores/payments-sellers/details-from-discount-type`,
        data
    ).then((res) => {
        response = res.data;
    });
    return response;
};

export const getTicketOfSeller = async (seller_id, payment_id) => {
    let data = [];
    await axios["get"](
        `/vendedores/payments-sellers/${seller_id}/${payment_id}/get-ticket-of-seller`
    ).then((response) => {
        data = response.data;
    });
    return data;
};

export const dowloadTicket = async (seller_id, payment_id) => {
    let data = [];
    await axios({
        url: `/vendedores/payments-sellers/${seller_id}/${payment_id}/download-receipt-pdf`,
        method: "GET",
        responseType: "blob",
    }).then((response) => {
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", "Ticket.pdf");
        document.body.appendChild(link);
        link.click();
    });
    return data;
};

export const updatePayment = async (id, data) => {
    let response = {};
    await axios["post"](`/vendedores/payments-sellers/${id}/update`, data).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

export const deletePayment = async (id) => {
    let response = {};
    await axios["delete"](`/vendedores/payments-sellers/${id}/destroy`).then(
        (res) => {
            response = res.data;
        }
    );
    return response;
};

// TRANSACTIONS
export const getTransactionsBySeller = async (
    id,
    startDate,
    endDate,
    methodPayment,
    page,
    rowsPerPage,
    search
) => {
    let result = { data: [], total: 0 };
    await axios
        .get(
            `/vendedores/transacciones/${id}/${startDate}/${endDate}/${methodPayment}/get-transactions-by-seller`,
            {
                params: {
                    page,
                    rowsPerPage,
                    search,
                },
            }
        )
        .then((response) => {
            result = {
                data: response.data.data,
                total: response.data.total,
            };
        })
        .catch((error) => {
            console.error("Error fetching data:", error);
        });
    return result;
};

export const saveSignature = async (id, signature) => {
    let response = null;
    await axios
        .post(`/vendedores/payments-sellers/payment-signature/${id}`, {
            signature,
        })
        .then((res) => {
            response = res.data;
        })
        .catch((e) => {
            response = null;
        });
    return response;
};
