export const requestHomeStatisticsForTarjetsByStatus = async () => {
    let data = {}
    await axios["post"](`/get-home-statistics-for-tarjets-by-status-c`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestStatisticsForTextCardInDashBoard = async () => {
    let data = {}
    await axios["post"](`/get-home-statistics-for-text-card-in-dashboard-c`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestStatsClientCardInDashBoard = async () => {
    let data = {}
    await axios["post"](`/get-stats-client-card-in-dashboard-c`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestStatsTicketCardInDashBoard = async () => {
    let data = {}
    await axios["post"](`/get-stats-ticket-card-in-dashboard-c`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestStatsFinanceCardInDashBoard = async () => {
    let data = {}
    await axios["post"](`/get-stats-finance-card-in-dashboard-c`, {}).then((response) => {
        data = response.data;
    });
    return data;
}

export const requestStatsServerCardInDashBoard = async () => {
    let data = {}
    await axios["post"](`/get-stats-server-card-in-dashboard-c`, {}).then((response) => {
        data = response.data;
    });
    return data;
}




