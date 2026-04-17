const setLastTab = (panel, tab)=>{
    localStorage.setItem(`tab_panel_${panel}`, tab);
};

const getLastTab = (panel, tab) => {
    let last = localStorage.getItem(`tab_panel_${panel}`) ?? null;
    if (!last && tab) {
        setLastTab(panel, tab);
        last = tab;
    }
    return last;
};

export function useTabs() {
    return {
        setLastTab,
        getLastTab,
    };
}
