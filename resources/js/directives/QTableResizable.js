class ResizableTable {
    constructor(table) {
        this.table = table;
        this.pageX = undefined;
        this.curCol = undefined;
        this.nxtCol = undefined;
        this.curColWidth = undefined;
        this.nxtColWidth = undefined;
        this.resizeHandle = undefined;

        this.boundOnMouseMove = this.onMouseMove.bind(this);
        this.boundOnMouseUp = this.onMouseUp.bind(this);

        this.setupResizing();
    }

    setupResizing() {
        const thead = this.table.querySelector("thead");
        if (!thead) return;

        const ths = thead.querySelectorAll("th");

        ths.forEach((th, index) => {
            if (index < ths.length - 1) {
                const handle = document.createElement("div");
                handle.className = "col-resize-handle";
                handle.addEventListener("mousedown", (e) => {
                    e.stopPropagation();
                    this.onMouseDown(e, ths, index);
                });
                th.style.position = "relative";
                th.appendChild(handle);
            }
        });

        document.addEventListener("mousemove", this.boundOnMouseMove);
        document.addEventListener("mouseup", this.boundOnMouseUp);
    }

    onMouseDown(e, ths, index) {
        e.preventDefault();

        this.curCol = ths[index];
        this.nxtCol = ths[index + 1];

        this.pageX = e.pageX;
        this.curColWidth = this.curCol.offsetWidth;

        if (this.nxtCol) {
            this.nxtColWidth = this.nxtCol.offsetWidth;
        }

        document.body.classList.add("resizing-column");
    }

    onMouseMove(e) {
        if (this.curCol) {
            const diff = e.pageX - this.pageX;

            let newCurWidth = this.curColWidth + diff;
            if (newCurWidth < 50) newCurWidth = 50;

            this.curCol.style.width = newCurWidth + "px";
            this.curCol.style.minWidth = newCurWidth + "px";

            if (this.nxtCol) {
                let newNxtWidth = this.nxtColWidth - diff;
                if (newNxtWidth < 50) newNxtWidth = 50;

                this.nxtCol.style.width = newNxtWidth + "px";
                this.nxtCol.style.minWidth = newNxtWidth + "px";
            }
        }
    }

    onMouseUp() {
        if (this.curCol) {
            const thToBlock = this.curCol;
            const clickBlocker = (e) => {
                e.stopPropagation();
                e.preventDefault();
                thToBlock.removeEventListener("click", clickBlocker, true);
            };
            thToBlock.addEventListener("click", clickBlocker, true);
        }
        this.curCol = undefined;
        this.nxtCol = undefined;
        this.pageX = undefined;
        document.body.classList.remove("resizing-column");
    }

    destroy() {
        document.removeEventListener("mousemove", this.boundOnMouseMove);
        document.removeEventListener("mouseup", this.boundOnMouseUp);
    }
}

const setupResizableTable = (el) => {
    if (el.__resizableTable__) {
        el.__resizableTable__.destroy();
        delete el.__resizableTable__;
    }

    const tableEl = el.querySelector(".q-table__container table");
    if (!tableEl) return;

    const thead = tableEl.querySelector("thead");
    if (thead && thead.querySelector("th")) {
        el.__resizableTable__ = new ResizableTable(tableEl);
    }
};

export default {
    mounted(el, binding) {
        setupResizableTable(el);

        const config = { childList: true, subtree: true };

        const observer = new MutationObserver((mutationsList, observer) => {
            let shouldSetup = false;
            for (const mutation of mutationsList) {
                if (
                    mutation.type === "childList" &&
                    (mutation.target.classList?.contains(
                        "q-table__container"
                    ) ||
                        mutation.target.tagName === "THEAD" ||
                        mutation.target.classList?.contains("q-table__middle"))
                ) {
                    shouldSetup = true;
                    break;
                }
            }

            if (shouldSetup) {
                setupResizableTable(el);
            }
        });

        observer.observe(el, config);
        el.__tableObserver__ = observer;
    },

    updated(el, binding) {
        if (binding.value !== binding.oldValue) {
            setupResizableTable(el);
        }
    },

    unmounted(el) {
        if (el.__tableObserver__) {
            el.__tableObserver__.disconnect();
            delete el.__tableObserver__;
        }

        if (el.__resizableTable__) {
            el.__resizableTable__.destroy();
            delete el.__resizableTable__;
        }
    },
};
