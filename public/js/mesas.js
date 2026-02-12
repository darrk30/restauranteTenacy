document.addEventListener('alpine:init', () => {

    Alpine.store('modalPdv', {
        open: false,
        mesaId: null,
        pedidoId: null,
        personas: 1,
        tenant: window.APP_TENANT
    });

    Alpine.data('mesasLogic', () => ({
        /* === ESTADO === */
        menuOpen: false,
        menuPos: { x: 0, y: 0 },
        activeTable: { id: null, orderId: null, status: 'free' },

        /* === VARIABLES AUXILIARES === */
        pressing: false,
        pressTimer: null,
        spinnerTimer: null,
        touchPos: { x: 0, y: 0 },
        ignoreNextClick: false,

        init() {
            console.log('Mesas Logic Iniciado');

            // Watcher para desbloquear click al cerrar menú
            this.$watch('menuOpen', (value) => {
                if (value === false) {
                    setTimeout(() => { this.ignoreNextClick = false; }, 100);
                }
            });
        },

        openMenu(x, y, tableData) {
            this.menuOpen = false;
            if (x + 180 > window.innerWidth) x = window.innerWidth - 190;
            if (y + 200 > window.innerHeight) y = window.innerHeight - 210;

            this.menuPos = { x, y };
            this.activeTable = tableData;
            this.ignoreNextClick = true; 

            this.$nextTick(() => { this.menuOpen = true; });
        },

        startPress(e, tableData) {
            if (e.touches.length !== 1) return;
            this.touchPos = { x: e.touches[0].clientX, y: e.touches[0].clientY };

            // A) Delay Visual (250ms)
            this.spinnerTimer = setTimeout(() => { this.pressing = true; }, 250);

            // B) Delay Acción (750ms)
            this.pressTimer = setTimeout(() => {
                this.openMenu(this.touchPos.x, this.touchPos.y, tableData);
                this.pressing = false; // El spinner se va porque ya abrió el menú
                if (navigator.vibrate) navigator.vibrate(50);
            }, 750);
        },

        /* 🔥 AQUÍ ESTÁ LA CORRECCIÓN 🔥 */
        cancelPress() {
            // Si 'pressing' es true, significa que el usuario vio el spinner
            // (mantuvo el dedo más de 250ms) pero soltó antes de los 750ms.
            // En este caso, BLOQUEAMOS el click siguiente.
            if (this.pressing) {
                this.ignoreNextClick = true;
                // Liberamos el bloqueo rápido para no afectar futuros clicks
                setTimeout(() => { this.ignoreNextClick = false; }, 100);
            }

            clearTimeout(this.spinnerTimer);
            clearTimeout(this.pressTimer);
            this.pressing = false;
        },

        handleCardClick(tableData) {
            if (this.menuOpen) return;
            
            // Si hay bloqueo activo (por menú cerrado o por carga cancelada), salimos.
            if (this.ignoreNextClick) return;

            if (tableData.status !== 'free' && tableData.orderId) {
                window.location = '/app/' + window.APP_TENANT + '/orden-mesa/' + tableData.id + '/' + tableData.orderId;
            } else {
                this.$store.modalPdv.open = true;
                this.$store.modalPdv.mesaId = tableData.id;
            }
        }
    }));
});